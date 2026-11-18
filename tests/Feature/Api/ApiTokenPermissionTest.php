<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Api\ApiAbilities;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

/*
 * API anahtarının yetkisi her istekte sahibinin güncel panel izniyle sınırlanır:
 * rolü değişen yöneticinin eski anahtarı kaybedilen izni kullanamaz.
 */

beforeEach(function () {
    Notification::fake();

    $this->role = Role::query()->create(['name' => 'Sipariş Sorumlusu', 'guard_name' => 'web']);
    $this->permission = Permission::query()->firstOrCreate(['name' => 'panel.shop.orders.index', 'guard_name' => 'web']);
    $this->role->givePermissionTo($this->permission);

    $this->user = User::factory()->state(['user_type' => UserType::Admin])->withRole('Sipariş Sorumlusu')->create();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function tokenRequest(string $token): mixed
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

test('a token stops using an ability when its owner loses the matching panel permission', function () {
    $token = $this->user->createToken('erp', [ApiAbilities::ORDERS_READ])->plainTextToken;

    tokenRequest($token)->getJson(route('api.v1.orders.index'))->assertOk();
    tokenRequest($token)->getJson(route('api.v1.index'))->assertOk()->assertJsonPath('data.token.abilities', [ApiAbilities::ORDERS_READ]);

    $this->role->revokePermissionTo($this->permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    tokenRequest($token)->getJson(route('api.v1.orders.index'))->assertForbidden();
    tokenRequest($token)->getJson(route('api.v1.index'))->assertOk()->assertJsonPath('data.token.abilities', []);
});

test('a token works again when the permission is given back, and unmapped abilities stay free', function () {
    $token = $this->user->createToken('erp', [ApiAbilities::ORDERS_READ])->plainTextToken;

    $this->role->revokePermissionTo($this->permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    tokenRequest($token)->getJson(route('api.v1.orders.index'))->assertForbidden();

    $this->role->givePermissionTo($this->permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    tokenRequest($token)->getJson(route('api.v1.orders.index'))->assertOk();

    // Panel eşlemesi olmayan (eklentinin eklediği) yetki sahibinin iznine bağlı değildir.
    expect(ApiAbilities::allowedFor($this->user, 'eklenti:okuma'))->toBeTrue();
});
