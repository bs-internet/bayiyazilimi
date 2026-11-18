<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Enums\UserStatus;
use App\Mail\Shop\AccountStatementMail;
use App\Mail\Shop\DueReminderMail;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\DealerGroup;
use App\Models\DueReminder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Shop\AccountStatementNotification;
use App\Notifications\Shop\DueReminderNotification;
use App\Services\Shop\Basket;
use App\Services\Shop\CreditService;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Carrier::query()->update(['status' => Status::Passive->value]);

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->group = DealerGroup::query()->create([
        'name' => 'Vadeli Bayiler', 'discount_rate' => 0,
        'payment_terms' => 'credit', 'credit_limit' => 10000, 'payment_term_days' => 30,
        'over_limit_action' => 'approval', 'overdue_action' => 'allow',
    ]);
    $this->account = $this->member->account;
    $this->account->update(['type' => CustomerType::Dealer, 'dealer_group_id' => $this->group->id, 'discount_rate' => 0, 'invoice_email' => 'Muhasebe@Bayi.test']);

    $try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'HT-1', 'name' => 'Hatırlatma Ürünü', 'slug' => 'hatirlatma-urunu', 'price' => 100,
        'currency_id' => $try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 100, StockMovementType::In);

    $this->place = function () use ($try): Order {
        $this->actingAs($this->member);
        $cart = Basket::cart();
        $cart->add($this->product->fresh(), 1);

        return app(OrderService::class)->placeFromCart($cart, [
            'email' => 'bayi@example.com', 'phone' => '05550000000',
            'billing' => ['type' => 'corporate', 'name' => 'Bayi', 'address' => 'Adres', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'same_address' => true, 'payment_method' => 'on_account',
        ], $this->member->fresh(), $try);
    };

    $this->remind = fn () => $this->artisan('shop:send-due-reminders')->assertSuccessful();
    $this->sentReminders = fn (): int => Notification::sent(new AnonymousNotifiable, DueReminderNotification::class)->count();
});

it('does not send due reminders while the setting is off', function () {
    ($this->place)();
    $this->travel(31)->days();

    ($this->remind)();

    expect(($this->sentReminders)())->toBe(0)
        ->and(DueReminder::query()->count())->toBe(0);
});

it('reminds an upcoming debt once and an overdue debt again after the repeat interval', function () {
    ShopSettings::save(['due_reminders_enabled' => true, 'due_warning_days' => '7', 'overdue_reminder_repeat_days' => '7']);
    $order = ($this->place)(); // vade 30 gün sonra

    // Vadeye 10 gün var: uyarı süresinin dışında.
    $this->travel(20)->days();
    ($this->remind)();
    expect(($this->sentReminders)())->toBe(0);

    // Vadeye 7 gün: "yaklaşıyor" bir kez.
    $this->travel(3)->days();
    ($this->remind)();
    ($this->remind)();
    expect(($this->sentReminders)())->toBe(1);

    Notification::assertSentOnDemand(DueReminderNotification::class, function (DueReminderNotification $n, array $channels, AnonymousNotifiable $to) use ($order) {
        return $to->routes['mail'] === ['user@user.com', 'muhasebe@bayi.test']
            && $n->overdue === [] && $n->upcoming[0]['order']['number'] === $order->number && $n->upcoming[0]['days'] === 7;
    });

    // Vade geçti: "vadesi geçti" hemen, sonra 7 günde bir.
    $this->travel(8)->days();
    ($this->remind)();
    expect(($this->sentReminders)())->toBe(2);

    $this->travel(3)->days();
    ($this->remind)();
    expect(($this->sentReminders)())->toBe(2);

    $this->travel(4)->days();
    ($this->remind)();
    expect(($this->sentReminders)())->toBe(3);

    $mail = (new DueReminderMail($this->account->fresh(), app(CreditService::class)->dues($this->account->fresh(), 7)['overdue'], []))->render();
    expect($mail)->toContain($order->number)->toContain('gün geçti');
});

it('sends an overdue reminder only once when the repeat interval is zero', function () {
    ShopSettings::save(['due_reminders_enabled' => true, 'due_warning_days' => '0', 'overdue_reminder_repeat_days' => '0']);
    ($this->place)();

    $this->travel(31)->days();
    ($this->remind)();
    $this->travel(30)->days();
    ($this->remind)();

    expect(($this->sentReminders)())->toBe(1);
});

it('skips accounts without an active member or invoice e-mail and reminds them later', function () {
    ShopSettings::save(['due_reminders_enabled' => true]);
    ($this->place)();
    $this->member->update(['status' => UserStatus::Passive]);
    $this->account->update(['invoice_email' => null]);

    $this->travel(31)->days();
    ($this->remind)();

    expect(($this->sentReminders)())->toBe(0)
        ->and(DueReminder::query()->count())->toBe(0);

    $this->account->update(['invoice_email' => 'muhasebe@bayi.test']);
    ($this->remind)();
    expect(($this->sentReminders)())->toBe(1);
});

it('sends the statement of the chosen period by e-mail with the Excel file attached', function () {
    ($this->place)();

    $this->actingAs($this->admin)
        ->post(route('panel.shop.ledger.send', $this->account->id), ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()])
        ->assertSessionHas('toast.type', 'success');

    Notification::assertSentOnDemand(AccountStatementNotification::class, function (AccountStatementNotification $n, array $channels, AnonymousNotifiable $to) {
        return $to->routes['mail'] === ['user@user.com', 'muhasebe@bayi.test'] && $n->from === now()->startOfMonth()->toDateString();
    });

    $mail = new AccountStatementMail($this->account->fresh(), null, null);
    expect($mail->render())->toContain($this->account->code)
        ->and($mail->attachments())->toHaveCount(1);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'ledger', 'event' => 'ledger_statement_sent']);
});

it('refuses to send the statement when the account has no address', function () {
    $this->member->update(['status' => UserStatus::Passive]);
    $this->account->update(['invoice_email' => null]);

    $this->actingAs($this->admin)
        ->post(route('panel.shop.ledger.send', $this->account->id))
        ->assertSessionHas('toast.type', 'warning');

    Notification::assertNothingSent();
});

it('shows the credit limit to the dealer only when it is defined and the setting allows it', function () {
    $limit = fn () => $this->actingAs($this->member->fresh())->get(route('app.shop.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('ledger.summary'))
        ->inertiaProps('ledger.summary.show_limit');

    expect($limit())->toBeTrue();

    ShopSettings::save(['show_credit_limit' => false]);
    expect($limit())->toBeFalse();

    ShopSettings::save(['show_credit_limit' => true]);
    $this->group->update(['credit_limit' => null]);
    $this->account->update(['credit_limit' => null]);
    expect($limit())->toBeFalse();

    // Yönetici müşteri detayında her durumda görür.
    $this->flushSession();
    ShopSettings::save(['show_credit_limit' => false]);
    $this->actingAs($this->admin)->get(route('panel.members.show', $this->member->id))
        ->assertInertia(fn (Assert $page) => $page->where('ledger.summary.show_limit', true)
            ->where('ledger.recipients', ['user@user.com', 'muhasebe@bayi.test']));
});
