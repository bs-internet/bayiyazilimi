<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Api\ApiAbilities;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create(['name' => 'Ayşe Yönetici']);
});

test('the api tokens screen lists every admin token with owner and abilities', function () {
    $other = User::factory()->admin()->create(['name' => 'Mehmet Yönetici']);
    $other->createToken('ERP', [ApiAbilities::ORDERS_READ]);
    $this->admin->createToken('Muhasebe', [ApiAbilities::PRODUCTS_READ, ApiAbilities::STOCK_WRITE]);

    $this->actingAs($this->admin)
        ->get(route('panel.settings.api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/settings/api-tokens/index')
            ->has('tokens', 2)
            ->where('plainToken', null)
            ->where('tokens', fn ($tokens) => collect($tokens)->pluck('owner')->sort()->values()->all() === ['Ayşe Yönetici', 'Mehmet Yönetici'])
            ->where('tokens', fn ($tokens) => collect($tokens)->firstWhere('name', 'Muhasebe')['abilities'][0]['label'] === __('Ürünleri okuma'))
            ->missing('abilities'));
});

test('a new token is created on its own page', function () {
    $this->actingAs($this->admin)
        ->get(route('panel.settings.api-tokens.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/settings/api-tokens/create')
            ->has('abilities', count(ApiAbilities::all())));
});

test('creating a token saves its abilities, logs it and shows the plain token once', function () {
    $response = $this->actingAs($this->admin)->post(route('panel.settings.api-tokens.store'), [
        'name' => 'ERP',
        'abilities' => [ApiAbilities::PRODUCTS_READ, ApiAbilities::ORDERS_WRITE],
        'expires_at' => now()->addMonth()->toDateString(),
    ]);

    $response->assertRedirect(route('panel.settings.api-tokens.index'))->assertSessionHas('api_token');

    $token = PersonalAccessToken::query()->sole();
    $plain = session('api_token');

    expect($token->name)->toBe('ERP')
        ->and($token->abilities)->toBe([ApiAbilities::PRODUCTS_READ, ApiAbilities::ORDERS_WRITE])
        ->and($token->tokenable_id)->toBe($this->admin->id)
        ->and($token->expires_at?->toDateString())->toBe(now()->addMonth()->toDateString())
        ->and($plain)->toStartWith($token->id.'|')
        ->and(PersonalAccessToken::findToken($plain)?->is($token))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'panel.api-token')->where('event', 'created')->exists())->toBeTrue();

    // Yönlendirilen sayfa anahtarı bir kez gösterir; sonraki açılışta yoktur.
    $this->actingAs($this->admin)->get(route('panel.settings.api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page->where('plainToken', $plain));

    $this->actingAs($this->admin)->get(route('panel.settings.api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page->where('plainToken', null));
});

test('token creation validates name, abilities and expiry', function () {
    $this->actingAs($this->admin)->post(route('panel.settings.api-tokens.store'), [
        'name' => '',
        'abilities' => ['everything:write'],
        'expires_at' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors(['name', 'abilities.0', 'expires_at']);

    $this->actingAs($this->admin)->post(route('panel.settings.api-tokens.store'), ['name' => 'X', 'abilities' => []])
        ->assertSessionHasErrors('abilities');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('a token can be revoked and stops working', function () {
    $plain = $this->admin->createToken('ERP', [ApiAbilities::PRODUCTS_READ])->plainTextToken;
    $token = PersonalAccessToken::query()->sole();

    $this->actingAs($this->admin)->delete(route('panel.settings.api-tokens.destroy', $token->id))
        ->assertRedirect(route('panel.settings.api-tokens.index'));

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(Activity::query()->where('log_name', 'panel.api-token')->where('event', 'revoked')->exists())->toBeTrue();

    $this->app['auth']->forgetGuards();
    $this->withToken($plain)->getJson(route('api.v1.products.index'))->assertUnauthorized();
});

test('the api tokens screen is protected by the route permission convention', function () {
    $admin = User::factory()->create(['user_type' => 'admin']);

    $this->actingAs($admin)->get(route('panel.settings.api-tokens.index'))->assertForbidden();
});

test('a token cannot carry an ability its owner lacks on the panel', function () {
    $staff = User::factory()->withRole('Depo')->create(['user_type' => UserType::Admin]);
    $staff->givePermissionTo([
        Permission::findOrCreate('panel.settings.api-tokens.index'),
        Permission::findOrCreate('panel.settings.api-tokens.create'),
        Permission::findOrCreate('panel.settings.api-tokens.store'),
        Permission::findOrCreate('panel.shop.products.index'),
    ]);

    $this->actingAs($staff)->post(route('panel.settings.api-tokens.store'), [
        'name' => 'ERP',
        'abilities' => [ApiAbilities::LEDGER_WRITE],
    ])->assertSessionHasErrors('abilities.0');

    $this->actingAs($staff)->post(route('panel.settings.api-tokens.store'), [
        'name' => 'ERP',
        'abilities' => [ApiAbilities::PRODUCTS_READ],
    ])->assertSessionHasNoErrors();
});
