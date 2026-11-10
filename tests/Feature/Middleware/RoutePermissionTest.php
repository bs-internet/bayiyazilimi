<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Sistem rollerini hazır et — admin@admin.com benzeri akışlar için.
    $this->seed(RolePermissionSeeder::class);
});

test('Super Admin bypasses Gate::before and reaches any panel route', function () {
    $superAdmin = User::factory()->admin()->create();

    $this->actingAs($superAdmin)
        ->get(route('panel.settings.permissions.index'))
        ->assertOk();
});

test('admin without Super Admin role and without the specific permission gets 403', function () {
    $admin = User::factory()
        ->state(['user_type' => UserType::Admin])
        ->create();
    // admin() factory'sini bilerek atladık; Super Admin rolü atanmadı. Admin görevi
    // kurulumda tüm panel yetkilerini aldığı için yetkisiz yeni bir görev kullanılır.
    $admin->syncRoles([Role::query()->create(['name' => 'Yetkisiz Görev', 'guard_name' => 'web'])]);

    $this->actingAs($admin)
        ->get(route('panel.settings.permissions.index'))
        ->assertForbidden();
});

test('admin with the specific permission can access the matching route', function () {
    $admin = User::factory()
        ->state(['user_type' => UserType::Admin])
        ->create();
    $adminRole = Role::query()->create(['name' => 'Yetki Görevi', 'guard_name' => 'web']);
    // Panel yetkileri kurulumda oluşturulur (PanelPermissionSeeder).
    $permission = Permission::query()->where('name', 'panel.settings.permissions.index')->firstOrFail();
    $adminRole->givePermissionTo($permission);
    $admin->syncRoles([$adminRole]);

    $this->actingAs($admin)
        ->get(route('panel.settings.permissions.index'))
        ->assertOk();
});

test('exempt route (dashboard) is reachable without any permission', function () {
    $admin = User::factory()
        ->state(['user_type' => UserType::Admin])
        ->create();
    // Hiç rol yok, hiç izin yok.

    $this->actingAs($admin)
        ->get(route('panel.dashboard'))
        ->assertOk();
});

test('an unnamed panel route sends other admins back with an explanation, Super Admin passes', function () {
    Route::middleware(['web', 'auth', 'route_permission'])->get('panel/adsiz-sayfa', fn () => 'açık');

    $admin = User::factory()->state(['user_type' => UserType::Admin])->create();
    $admin->syncRoles(['Admin']);

    $this->actingAs($admin)
        ->from(route('panel.dashboard'))
        ->get('/panel/adsiz-sayfa')
        ->assertRedirect(route('panel.dashboard'))
        ->assertSessionHas('toast.type', 'warning');

    $this->actingAs($admin)->getJson('/panel/adsiz-sayfa')->assertForbidden()->assertJsonStructure(['message']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/panel/adsiz-sayfa')
        ->assertOk()
        ->assertSee('açık');
});
