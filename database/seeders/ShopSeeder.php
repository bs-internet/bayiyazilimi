<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shop\RateSource;
use App\Enums\Status;
use App\Models\Carrier;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Mağazanın temel verileri: para birimleri + kurlar, KDV %0, ürün birimleri, varsayılan depo,
 * yasal/kurumsal sayfa taslakları, örnek kargo firmaları ve mağaza ayarları.
 * Tekrar çalıştırılabilir (updateOrCreate).
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $this->currencies();
        $this->taxes();
        $this->units();
        $this->warehouses();
        $this->contracts();
        $this->carriers();
        $this->settings();
    }

    private function currencies(): void
    {
        Currency::query()->updateOrCreate(['code' => 'TRY'], [
            'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2,
            'thousands_separator' => '.', 'decimal_separator' => ',',
            'exchange_rate' => 1, 'rate_source' => RateSource::Manual,
            'status' => Status::Active, 'sort_order' => 1,
        ]);

        // Başlangıç kurları yaklaşık değerlerdir; TCMB'den güncellenir (shop:sync-rates / panel).
        foreach ([
            ['USD', 'Amerikan Doları', '$', 42.0, 2],
            ['EUR', 'Euro', '€', 48.5, 3],
        ] as [$code, $name, $symbol, $rate, $order]) {
            $currency = Currency::withTrashed()->firstOrNew(['code' => $code]);
            $currency->fill([
                'name' => $name, 'symbol' => $symbol, 'decimal_places' => 2,
                'thousands_separator' => ',', 'decimal_separator' => '.',
                'rate_source' => RateSource::Tcmb, 'status' => Status::Active, 'sort_order' => $order,
            ]);

            if (! $currency->exists || Money::decimal($currency->exchange_rate)->isLessThanOrEqualTo(1)) {
                $currency->exchange_rate = $rate;
            }

            $currency->deleted_at = null;
            $currency->save();
        }
    }

    private function taxes(): void
    {
        Tax::query()->updateOrCreate(['name' => 'KDV %0'], [
            'rate' => 0, 'status' => Status::Active, 'sort_order' => 3,
        ]);
    }

    /**
     * Ürün birimleri (panelden düzenlenir).
     */
    private function units(): void
    {
        foreach (['Adet', 'Paket', 'Kutu', 'Koli', 'Set', 'Takım', 'Rulo', 'Litre', 'Kg', 'Metre'] as $i => $name) {
            Unit::query()->firstOrCreate(['name' => $name], [
                'status' => Status::Active,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function warehouses(): void
    {
        foreach ([
            ['DEPO', 'Merkez Depo', true, 1],
        ] as [$code, $name, $default, $order]) {
            Warehouse::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'is_default' => $default,
                'is_sellable' => true,
                'status' => Status::Active,
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * Sözleşme taslakları (Tanımlamalar → Sözleşmeler). Yalnız başlık ve
     * "metni girin" notuyla, **pasif** oluşturulur: yönetici metni yazıp
     * aktifleştirene kadar hiçbir formda ya da bayi panelinde görünmez.
     * Yöneticinin düzenlediği sözleşmeye dokunulmaz.
     */
    private function contracts(): void
    {
        $marker = 'Bu sözleşmenin metni henüz girilmedi.';

        foreach ([
            // slug, başlık, ödeme formu, üyelik formu, sıra
            ['satis-sozlesmesi', 'Satış Sözleşmesi', true, false, 1],
            ['uyelik-sozlesmesi', 'Üyelik Sözleşmesi', false, true, 2],
            ['kvkk-aydinlatma-metni', 'KVKK Aydınlatma Metni', true, true, 3],
            ['garanti-ve-iade-sartlari', 'Garanti ve İade Şartları', false, false, 4],
        ] as [$slug, $title, $checkout, $registration, $order]) {
            $contract = Contract::query()->firstOrNew(['slug' => $slug]);

            if ($contract->exists && ! str_contains((string) $contract->content, $marker)) {
                continue;
            }

            $contract->fill([
                'title' => $title,
                'content' => "<p><em>{$marker} Tanımlamalar → Sözleşmeler ekranından şirketinizin metnini girip sözleşmeyi aktifleştirin.</em></p>",
                'show_on_checkout' => $checkout,
                'show_on_registration' => $registration,
                'status' => $contract->status ?? Status::Passive,
                'sort_order' => $order,
            ])->save();
        }
    }

    /**
     * Örnek kargo firmaları. Desi fiyatları ÖRNEKTİR; firmanın güncel Excel'i
     * panelden (Kargo Firmaları → Excel'den yükle) yüklenmelidir.
     */
    private function carriers(): void
    {
        if (Carrier::query()->exists()) {
            return;
        }

        $sample = [[0, 1, 120], [1, 3, 150], [3, 5, 185], [5, 10, 260], [10, 15, 340], [15, 20, 420], [20, 30, 560]];

        foreach ([
            ['YURTICI', 'Yurtiçi Kargo', 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={code}', true, 1],
            ['ARAS', 'Aras Kargo', 'https://kargotakip.araskargo.com.tr/mainpage.aspx?code={code}', false, 2],
            ['MNG', 'MNG Kargo', 'https://www.mngkargo.com.tr/gonderitakip/{code}', false, 3],
        ] as [$code, $name, $tracking, $default, $order]) {
            $carrier = Carrier::create([
                'code' => $code,
                'name' => $name,
                'tracking_url' => $tracking,
                'extra_desi_price' => 20,
                'prices_include_tax' => true,
                'delivery_days' => 3,
                'is_default' => $default,
                'status' => $default ? Status::Active : Status::Passive,
                'sort_order' => $order,
            ]);

            foreach ($sample as [$min, $max, $price]) {
                $carrier->rates()->create(['min_desi' => $min, 'max_desi' => $max, 'price' => $price]);
            }
        }
    }

    private function settings(): void
    {
        // Marka adı / slogan / logo panelden (Ayarlar › Genel Ayarlar) değiştirilir;
        // logo yüklenene kadar varsayılan görseller (Branding) kullanılır.
        Setting::query()->updateOrCreate(['key' => 'app_name'], ['value' => 'Bayi Yazılımı', 'group' => 'general']);
        Setting::query()->updateOrCreate(['key' => 'app_slogan'], ['value' => 'Bayi ve E-Ticaret Yönetimi', 'group' => 'general']);
    }
}
