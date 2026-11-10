<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * API anahtarı oluşturma ayrı sayfaya taşındı (panel.settings.api-tokens.create).
 * Kurulu sistemde anahtar oluşturabilen roller yeni sayfanın yetkisini de alır.
 * Yeni kurulumda yetkiyi kurulum (PanelPermissionSeeder) oluşturur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $store = Permission::query()->where('name', 'panel.settings.api-tokens.store')->where('guard_name', 'web')->first();

        if ($store === null) {
            return;
        }

        $create = Permission::query()->firstOrCreate(
            ['name' => 'panel.settings.api-tokens.create', 'guard_name' => 'web'],
            ['group' => $store->group, 'label' => __('API anahtarı oluşturma ekranı')],
        );

        Role::query()->whereHas('permissions', fn ($q) => $q->whereKey($store->getKey()))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($create));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'panel.settings.api-tokens.create')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
