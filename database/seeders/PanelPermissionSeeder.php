<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Panel\Settings\Permissions\PermissionService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Panelin tüm ekranlarının yetkilerini oluşturur ("Rotalardan Keşfet" ile aynı
 * liste: panel rotaları + modüllerin yetki kaydı) ve **Admin** görevine hepsini
 * atar. Böylece kurulumdan sonra Admin, Super Admin'e gerek kalmadan müşteri,
 * kullanıcı, ayar ve tanımlama ekranlarını kullanabilir. Diğer görevler
 * (ShopRoleSeeder) yalnız kendi alanlarının yetkilerini alır.
 *
 * Tekrar çalıştırılabilir: var olan (silinmiş dahil) yetkilere dokunmaz, Admin'e
 * eksik yetkileri ekler.
 */
class PanelPermissionSeeder extends Seeder
{
    public function run(PermissionService $permissions): void
    {
        $permissions->createDiscovered();

        Role::query()
            ->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web'])
            ->givePermissionTo(Permission::query()->pluck('name')->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
