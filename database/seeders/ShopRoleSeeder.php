<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Providers\ShopServiceProvider;
use App\Support\Registry\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mağaza yetkilerini DB'ye işler ve görev bazlı rolleri oluşturur.
 *
 * - Admin               : tüm mağaza yetkileri
 * - Mağaza Yöneticisi   : tüm mağaza yetkileri
 * - Satış Sorumlusu     : siparişler, teklifler, ürün/stok görüntüleme, kurlar
 * - Depo Sorumlusu      : stok, depolar, sipariş görüntüleme/durum, kargo
 * - Katalog Sorumlusu   : ürün, kategori, marka, içe/dışa aktarma, stok görüntüleme
 *
 * İçerik Editörü rolü vitrin modülüyle gelir (içerik ekranları onundur).
 *
 * Super Admin her şeye Gate::before ile sahiptir; ona izin atanmaz.
 */
class ShopRoleSeeder extends Seeder
{
    public function run(PermissionRegistry $registry): void
    {
        $definitions = $registry->forSource(ShopServiceProvider::SOURCE);

        foreach ($definitions as $definition) {
            Permission::query()->withTrashed()->updateOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                ['group' => $definition['group'] ?? null, 'label' => $definition['label'] ?? null, 'deleted_at' => null],
            );
        }

        $all = collect($definitions)->pluck('name');
        $pick = fn (array $prefixes) => $all->filter(fn (string $name) => Str::startsWith($name, $prefixes))->values()->all();

        $roles = [
            'Admin' => $all->all(),
            'Mağaza Yöneticisi' => $all->all(),
            'Satış Sorumlusu' => array_merge(
                $pick(['panel.shop.orders.', 'panel.shop.quotes.', 'panel.shop.returns.', 'panel.shop.exchange-rates.index']),
                ['panel.shop.products.index', 'panel.shop.stock.index', 'panel.shop.carriers.index'],
            ),
            'Depo Sorumlusu' => array_merge(
                $pick(['panel.shop.stock.', 'panel.shop.stock-receipts.', 'panel.shop.stock-counts.', 'panel.shop.suppliers.', 'panel.shop.warehouses.', 'panel.shop.warehouse-locations.', 'panel.shop.carriers.', 'panel.shop.returns.']),
                ['panel.shop.orders.index', 'panel.shop.orders.show', 'panel.shop.orders.status', 'panel.shop.orders.change-lot', 'panel.shop.orders.pick-list', 'panel.shop.products.index'],
            ),
            'Katalog Sorumlusu' => array_merge(
                $pick(['panel.shop.products.', 'panel.shop.categories.', 'panel.shop.brands.', 'panel.shop.import.', 'panel.shop.export.']),
                ['panel.shop.stock.index', 'panel.shop.stock.movements', 'panel.shop.exchange-rates.index'],
            ),
        ];

        foreach ($roles as $name => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
