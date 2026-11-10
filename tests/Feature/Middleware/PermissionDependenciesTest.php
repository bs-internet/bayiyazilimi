<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\UserType;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Panel\Settings\Roles\RoleService;
use App\Support\Permissions\PermissionDependencies;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();

    foreach (['panel.shop.orders.index', 'panel.shop.orders.show', 'panel.shop.orders.invoice', 'panel.shop.orders.status'] as $name) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['group' => 'Siparişler', 'label' => $name]);
    }

    app()->forgetScopedInstances();
});

function staffWith(array $permissions): User
{
    $user = User::factory()->state(['user_type' => UserType::Admin])->create();
    $role = Role::query()->create(['name' => 'Görev '.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($permissions);
    $user->syncRoles([$role]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

test('action permissions require the section view permissions by naming convention', function () {
    $deps = app(PermissionDependencies::class);

    expect($deps->requires('panel.shop.orders.invoice'))->toBe(['panel.shop.orders.show'])
        ->and($deps->requires('panel.shop.orders.show'))->toBe(['panel.shop.orders.index'])
        ->and($deps->requires('panel.shop.orders.index'))->toBe([])
        ->and($deps->withPrerequisites(['panel.shop.orders.invoice']))
        ->toEqualCanonicalizing(['panel.shop.orders.invoice', 'panel.shop.orders.show', 'panel.shop.orders.index']);
});

test('a permission without its prerequisites does not open the route', function () {
    // Görev doğrudan (servis dışından) eksik verilmiş olsa bile erişim yok.
    $user = staffWith(['panel.shop.orders.status']);

    $this->actingAs($user)->get(route('panel.shop.orders.index'))->assertForbidden();
    expect(app(PermissionDependencies::class)->satisfiedBy($user, 'panel.shop.orders.status'))->toBeFalse();

    $full = staffWith(['panel.shop.orders.index', 'panel.shop.orders.show', 'panel.shop.orders.status']);
    expect(app(PermissionDependencies::class)->satisfiedBy($full, 'panel.shop.orders.status'))->toBeTrue();

    // Sipariş detayı yetkisi var ama liste yetkisi yok → detay da açılmaz.
    $order = Order::query()->create([
        'number' => 'S-DEP-1', 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali', 'email' => 'a@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali'], 'currency_code' => 'TRY',
        'subtotal' => 0, 'tax_total' => 0, 'grand_total' => 0,
    ]);

    $this->flushSession();
    $this->actingAs(staffWith(['panel.shop.orders.show']))->get(route('panel.shop.orders.show', $order->id))->assertForbidden();

    $this->flushSession();
    $this->actingAs(staffWith(['panel.shop.orders.index', 'panel.shop.orders.show']))->get(route('panel.shop.orders.show', $order->id))->assertOk();
});

test('saving a role adds missing prerequisites and the role screen shows them', function () {
    $admin = User::factory()->admin()->create();
    $role = app(RoleService::class)->create(['name' => 'Muhasebe', 'permissions' => ['panel.shop.orders.invoice']], $admin);

    expect($role->permissions()->pluck('name')->all())
        ->toEqualCanonicalizing(['panel.shop.orders.invoice', 'panel.shop.orders.show', 'panel.shop.orders.index']);

    $this->actingAs($admin)->get(route('panel.settings.roles.show', $role->uuid ?? $role->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('permissionGroups', fn ($groups) => collect($groups)->flatten(1)
            ->firstWhere('name', 'panel.shop.orders.invoice')['requires'] === ['panel.shop.orders.show']));
});
