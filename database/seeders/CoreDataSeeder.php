<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Tax;
use App\Services\System\UpdateService;
use Illuminate\Database\Seeder;

/**
 * Temel veriler: her kurulumda yüklenir, kullanıcı tarafından tekrar girilmez.
 *
 * Diller, para birimleri, Türkiye + il / ilçe, KDV oranları, genel ayarlar,
 * mağaza temel verileri (birimler, ana depo, yasal sayfa taslakları, kargo
 * firmaları), sistem rolleri ile görev rolleri ve modüllerin başlangıç verileri
 * (`database.seed` kancası). Kullanıcı / hesap oluşturmaz.
 *
 * Tekrar çalıştırılabilir (updateOrCreate / firstOrCreate).
 */
class CoreDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->languages();
        $this->currencies();

        Country::query()->updateOrCreate(
            ['code' => 'TR'],
            ['name' => 'Türkiye', 'status' => Status::Active, 'sort_order' => 1],
        );

        $this->call(TurkiyeLocationsSeeder::class);

        $this->taxes();

        $this->call(SettingsSeeder::class);
        $this->call(ShopSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(ShopRoleSeeder::class);

        // Modüllerin başlangıç verileri (ör. vitrin menüleri ve ana sayfa bölümleri).
        // Callback parametresi `$seeder` adıyla bu seeder'ı alır: fn (Seeder $seeder) => $seeder->call(...).
        hooks()->do('database.seed', seeder: $this);

        // Panelin tüm ekranlarının yetkileri (Rotalardan Keşfet'in yaptığı) ve Admin
        // görevine atanması; modül yetkileri de dahil olsun diye en sonda.
        $this->call(PanelPermissionSeeder::class);

        // Veritabanı bu kod sürümüyle kuruldu ("güncelleme bekliyor" denetimi).
        app(UpdateService::class)->markCurrent();
    }

    private function languages(): void
    {
        Language::query()->updateOrCreate(
            ['code' => 'tr'],
            ['name' => 'Turkish', 'native_name' => 'Türkçe', 'status' => Status::Active, 'sort_order' => 1],
        );

        Language::query()->updateOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'native_name' => 'English', 'status' => Status::Active, 'sort_order' => 2],
        );
    }

    private function currencies(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => 'TRY'],
            [
                'name' => 'Türk Lirası',
                'symbol' => '₺',
                'decimal_places' => 2,
                'thousands_separator' => '.',
                'decimal_separator' => ',',
                'status' => Status::Active,
                'sort_order' => 1,
            ],
        );

        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'Amerikan Doları',
                'symbol' => '$',
                'decimal_places' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'status' => Status::Active,
                'sort_order' => 2,
            ],
        );
    }

    private function taxes(): void
    {
        Tax::query()->updateOrCreate(
            ['name' => 'KDV %20'],
            ['rate' => 20, 'status' => Status::Active, 'sort_order' => 1],
        );

        Tax::query()->updateOrCreate(
            ['name' => 'KDV %10'],
            ['rate' => 10, 'status' => Status::Active, 'sort_order' => 2],
        );
    }
}
