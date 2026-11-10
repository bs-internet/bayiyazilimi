<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Panel\Members\MemberEmailChangeRequestedNotification;
use App\Notifications\Panel\Members\MemberEmailVerifiedNotification;
use App\Notifications\Panel\Members\MemberStatusUpdatedNotification;
use App\Notifications\Panel\Members\MemberWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('panel members page lists only member users', function () {
    $viewer = User::factory()->admin()->create();
    $member = User::factory()->member()->create([
        'name' => 'Needle Member',
    ]);
    User::factory()->admin()->create([
        'name' => 'Needle Admin',
    ]);

    $this->actingAs($viewer)
        ->get(route('panel.members.index', ['q' => 'Needle']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/members/index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $member->id)
            ->where('users.data.0.user_type', 'member')
        );
});

test('panel member detail includes activity and session logs', function () {
    $viewer = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';

    activity('profile')
        ->causedBy($member)
        ->performedOn($member)
        ->event('updated')
        ->log('Profile updated');

    $member->authentications()->create([
        'ip_address' => '127.0.0.1',
        'user_agent' => $userAgent,
        'login_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('panel.members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/members/show')
            ->where('user.data.id', $member->id)
            ->has('activities.data', 1)
            ->where('activities.data.0.event', 'updated')
            ->has('sessions.data', 1)
            ->where('sessions.data.0.ip_address', '127.0.0.1')
            ->where('sessions.data.0.browser', 'Google Chrome')
        );
});

test('panel member detail rejects admin users', function () {
    $viewer = User::factory()->admin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($viewer)
        ->get(route('panel.members.show', $admin))
        ->assertNotFound();
});

test('panel admin can create a member and send welcome mail', function () {
    Notification::fake();

    $viewer = User::factory()->admin()->create();

    $this->actingAs($viewer)
        ->post(route('panel.members.store'), [
            'name' => 'Created Member',
            'email' => 'created-member@example.com',
            'status' => UserStatus::Active->value,
            'email_verified' => true,
        ])
        ->assertRedirect();

    $created = User::query()
        ->where('email', 'created-member@example.com')
        ->firstOrFail();

    expect($created->name)->toBe('Created Member')
        ->and($created->user_type->isMember())->toBeTrue()
        ->and($created->status)->toBe(UserStatus::Active)
        ->and($created->email_verified_at)->not->toBeNull();

    Notification::assertSentTo(
        $created,
        MemberWelcomeNotification::class,
        fn (MemberWelcomeNotification $notification): bool => $notification->verifiesEmail === false
            && str_contains($notification->welcomeUrl, 'token='),
    );

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $created->id,
        'causer_id' => $viewer->id,
        'event' => 'created',
    ]);
});

test('panel member welcome link verifies email before password reset when needed', function () {
    Notification::fake();

    $viewer = User::factory()->admin()->create();

    $this->actingAs($viewer)
        ->post(route('panel.members.store'), [
            'name' => 'Unverified Member',
            'email' => 'unverified-member@example.com',
            'status' => UserStatus::Active->value,
            'email_verified' => false,
        ])
        ->assertRedirect();

    $created = User::query()
        ->where('email', 'unverified-member@example.com')
        ->firstOrFail();

    expect($created->email_verified_at)->toBeNull();

    $welcomeUrl = null;

    Notification::assertSentTo(
        $created,
        MemberWelcomeNotification::class,
        function (MemberWelcomeNotification $notification) use (&$welcomeUrl): bool {
            $welcomeUrl = $notification->welcomeUrl;

            return $notification->verifiesEmail === true;
        },
    );

    expect($welcomeUrl)->toBeString();

    $response = $this->get($welcomeUrl);

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toContain('/reset-password/');

    expect($created->fresh()->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $created->id,
        'causer_id' => $created->id,
        'event' => 'welcome_accepted',
    ]);
});

test('panel admin can verify a member email address', function () {
    Notification::fake();

    $viewer = User::factory()->admin()->create();
    $member = User::factory()->member()->unverified()->create();

    $this->actingAs($viewer)
        ->post(route('panel.members.email.verify', $member))
        ->assertRedirect();

    expect($member->fresh()->email_verified_at)->not->toBeNull();

    Notification::assertSentTo($member, MemberEmailVerifiedNotification::class);

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $member->id,
        'causer_id' => $viewer->id,
        'event' => 'email_verified',
    ]);
});

test('panel admin can request a member email change and close sessions', function () {
    config(['session.driver' => 'database']);
    Notification::fake();

    $viewer = User::factory()->admin()->create();
    $member = User::factory()->member()->create();
    $newEmail = 'new-member@example.com';

    DB::table('sessions')->insert([
        'id' => 'session-id',
        'user_id' => $member->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature Test',
        'payload' => 'payload',
        'last_activity' => now()->getTimestamp(),
    ]);

    $authentication = $member->authentications()->create([
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature Test',
        'login_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->post(route('panel.members.email.change', $member), [
            'email' => $newEmail,
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('sessions', [
        'id' => 'session-id',
    ]);

    expect($authentication->fresh()->logout_at)->not->toBeNull();

    Notification::assertSentTo(
        $member,
        MemberEmailChangeRequestedNotification::class,
        fn (MemberEmailChangeRequestedNotification $notification): bool => $notification->email === $newEmail
            && str_contains($notification->confirmationUrl, 'email='),
    );

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $member->id,
        'causer_id' => $viewer->id,
        'event' => 'email_change_requested',
    ]);
});

test('panel member email change can be confirmed with a signed link', function () {
    config(['session.driver' => 'database']);

    $member = User::factory()->member()->create([
        'email' => 'old-member@example.com',
    ]);
    $newEmail = 'confirmed-member@example.com';

    DB::table('sessions')->insert([
        'id' => 'session-id',
        'user_id' => $member->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature Test',
        'payload' => 'payload',
        'last_activity' => now()->getTimestamp(),
    ]);

    $authentication = $member->authentications()->create([
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature Test',
        'login_at' => now(),
    ]);

    $url = URL::temporarySignedRoute(
        'panel.members.email.confirm',
        now()->addDay(),
        [
            'user' => $member->id,
            'email' => $newEmail,
        ],
    );

    $this->get($url)->assertRedirect(route('login'));

    $member->refresh();

    expect($member->email)->toBe($newEmail)
        ->and($member->email_verified_at)->not->toBeNull()
        ->and($authentication->fresh()->logout_at)->not->toBeNull();

    $this->assertDatabaseMissing('sessions', [
        'id' => 'session-id',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $member->id,
        'causer_id' => $member->id,
        'event' => 'email_changed',
    ]);
});

test('panel admin can update a member status', function () {
    Notification::fake();

    $viewer = User::factory()->admin()->create();
    $member = User::factory()->member()->create();

    $this->actingAs($viewer)
        ->patch(route('panel.members.status', $member), [
            'status' => UserStatus::Passive->value,
        ])
        ->assertRedirect();

    expect($member->fresh()->status)->toBe(UserStatus::Passive);

    Notification::assertSentTo(
        $member,
        MemberStatusUpdatedNotification::class,
        fn (MemberStatusUpdatedNotification $notification): bool => $notification->status === UserStatus::Passive,
    );

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $member->id,
        'causer_id' => $viewer->id,
        'event' => 'status_updated',
    ]);
});

test('panel members list and detail include order stats and account records', function () {
    $viewer = User::factory()->admin()->create();
    $account = Account::factory()->create(['title' => 'Stat Firma']);
    $member = User::factory()->member()->create(['name' => 'Stat Member', 'account_id' => $account->id]);

    $order = fn (array $attributes) => Order::query()->create(array_merge([
        'number' => 'S'.random_int(100000, 999999),
        'account_id' => $account->id,
        'status' => OrderStatus::Processing,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer',
        'customer_name' => 'Stat Member',
        'email' => 'stat@example.com',
        'phone' => '5550000000',
        'billing_address' => ['name' => 'Stat Member'],
        'shipping_address' => ['name' => 'Stat Member'],
        'currency_code' => 'TRY',
        'exchange_rate' => 1,
        'grand_total' => 100,
    ], $attributes));

    // Ödenmiş siparişlerin ödeme kayıtları (tüm ödemeler payments tablosunda).
    $paid = fn (Order $o) => $o->payments()->create([
        'account_id' => $account->id, 'method' => 'bank_transfer', 'provider' => 'manual',
        'merchant_oid' => 'P'.random_int(100000, 999999), 'amount' => $o->grand_total,
        'currency_code' => $o->currency_code, 'status' => PaymentStatus::Paid, 'paid_at' => now(),
    ]);

    $paid($order([]));
    $paid($order(['currency_code' => 'EUR', 'exchange_rate' => 40, 'grand_total' => 10]));
    $paid($order(['status' => OrderStatus::Cancelled, 'grand_total' => 999]));

    $this->actingAs($viewer)
        ->get(route('panel.members.index', ['q' => 'Stat Member']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.orders_count', 2)
            ->where('users.data.0.orders_total', fn ($v) => $v === '500.00')
        );

    $this->actingAs($viewer)
        ->get(route('panel.members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.stats.orders_count', 2)
            ->where('overview.orders.total', 3)
            ->has('overview.orders.data', 3)
            ->where('ledger.summary.side', 'zero')
            ->where('billing.title', 'Stat Firma')
        );

    $this->actingAs($viewer)
        ->get(route('panel.shop.orders.index', ['account' => $account->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 3));
});
