<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Shop\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Örnek katalog: kategoriler, markalar ve stoklu ürünler (hırdavat, elektrik,
 * tesisat, boya, iş güvenliği). Kurulumda "örnek verileri yükle" seçilince
 * yüklenir; panel, bayi alanı ve raporlar boş görünmesin diye.
 *
 * Marka adları uydurmadır. Ürünler `ORN-` ile başlayan stok koduyla açılır;
 * tekrar çalıştırılırsa var olan ürün atlanır (fiyatı / stoğu değişmez).
 */
class SampleCatalogSeeder extends Seeder
{
    /** Ana kategori => alt kategoriler. */
    private const CATEGORIES = [
        'Elektrik Malzemeleri' => ['Kablolar', 'Anahtar ve Prizler', 'Aydınlatma'],
        'Hırdavat' => ['El Aletleri', 'Bağlantı Elemanları'],
        'Tesisat' => ['Vanalar', 'Borular ve Ek Parçalar'],
        'Boya ve Yapı Kimyasalları' => ['Boyalar', 'Silikon ve Yapıştırıcılar'],
        'İş Güvenliği' => ['Eldivenler', 'Koruyucu Ekipman'],
    ];

    private const BRANDS = ['Atlas', 'Kuzey', 'Mavi Hat', 'Tekno Yapı', 'Delta Pro'];

    /**
     * [stok kodu, ad, alt kategori, marka, fiyat, liste fiyatı, birim, stok, desi, satış türü, yeni mi]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: ?string, 6: string, 7: int, 8: string, 9: SaleMode, 10: bool}>
     */
    private const PRODUCTS = [
        ['ORN-1001', 'NYA Kablo 2,5 mm² (100 m)', 'Kablolar', 'Mavi Hat', '1450.00', '1620.00', 'Rulo', 40, '4', SaleMode::Cart, false],
        ['ORN-1002', 'TTR Kablo 3x1,5 mm² (100 m)', 'Kablolar', 'Mavi Hat', '2890.00', null, 'Rulo', 25, '6', SaleMode::Cart, false],
        ['ORN-1003', 'NYY Yer Altı Kablosu 4x16 mm² (Makara)', 'Kablolar', 'Mavi Hat', '48500.00', null, 'Adet', 0, '0', SaleMode::Quote, false],
        ['ORN-1101', 'Sıva Altı Topraklı Priz, Beyaz', 'Anahtar ve Prizler', 'Kuzey', '68.50', null, 'Adet', 600, '0.2', SaleMode::Cart, false],
        ['ORN-1102', 'Sıva Altı Komütatör Anahtar, Beyaz', 'Anahtar ve Prizler', 'Kuzey', '72.00', null, 'Adet', 450, '0.2', SaleMode::Cart, false],
        ['ORN-1103', 'Üçlü Grup Priz, 3 m Kablolu', 'Anahtar ve Prizler', 'Kuzey', '245.00', '279.00', 'Adet', 120, '0.6', SaleMode::Cart, true],
        ['ORN-1201', 'LED Panel 60x60 40 W, Gün Işığı', 'Aydınlatma', 'Atlas', '415.00', null, 'Adet', 90, '3', SaleMode::Cart, false],
        ['ORN-1202', 'LED Ampul E27 9 W (10 lu Paket)', 'Aydınlatma', 'Atlas', '310.00', '360.00', 'Paket', 150, '1', SaleMode::Cart, true],
        ['ORN-1203', 'LED Projektör 100 W, IP65', 'Aydınlatma', 'Atlas', '890.00', null, 'Adet', 35, '2', SaleMode::Cart, false],
        ['ORN-2001', 'Akülü Vidalama 18 V, Çift Akülü', 'El Aletleri', 'Delta Pro', '3250.00', '3690.00', 'Adet', 18, '3', SaleMode::Cart, true],
        ['ORN-2002', 'Darbeli Matkap 750 W', 'El Aletleri', 'Delta Pro', '1980.00', null, 'Adet', 22, '3', SaleMode::Cart, false],
        ['ORN-2003', 'Tornavida Seti, 12 Parça', 'El Aletleri', 'Tekno Yapı', '385.00', null, 'Set', 70, '1', SaleMode::Cart, false],
        ['ORN-2101', 'Sunta Vidası 4x40 (500 lü Kutu)', 'Bağlantı Elemanları', 'Tekno Yapı', '165.00', null, 'Kutu', 300, '1', SaleMode::Cart, false],
        ['ORN-2102', 'Plastik Dübel 8 mm (100 lü Paket)', 'Bağlantı Elemanları', 'Tekno Yapı', '48.00', null, 'Paket', 500, '0.3', SaleMode::Cart, false],
        ['ORN-3001', 'Küresel Vana 1/2", Kelebek Kollu', 'Vanalar', 'Atlas', '135.00', null, 'Adet', 240, '0.3', SaleMode::Cart, false],
        ['ORN-3002', 'Küresel Vana 1", Kelebek Kollu', 'Vanalar', 'Atlas', '298.00', null, 'Adet', 110, '0.6', SaleMode::Cart, false],
        ['ORN-3101', 'PPR Boru 20 mm (4 m)', 'Borular ve Ek Parçalar', 'Kuzey', '62.00', null, 'Adet', 400, '1', SaleMode::Cart, false],
        ['ORN-3102', 'PPR Dirsek 20 mm 90° (25 li Paket)', 'Borular ve Ek Parçalar', 'Kuzey', '87.50', null, 'Paket', 180, '0.5', SaleMode::Cart, false],
        ['ORN-4001', 'İç Cephe Boyası 15 L, Beyaz', 'Boyalar', 'Mavi Hat', '1240.00', '1390.00', 'Adet', 60, '16', SaleMode::Cart, false],
        ['ORN-4002', 'Dış Cephe Boyası 15 L, Beyaz', 'Boyalar', 'Mavi Hat', '1690.00', null, 'Adet', 40, '16', SaleMode::Cart, false],
        ['ORN-4101', 'Şeffaf Silikon 310 ml (24 lü Koli)', 'Silikon ve Yapıştırıcılar', 'Tekno Yapı', '1150.00', null, 'Koli', 30, '8', SaleMode::Cart, false],
        ['ORN-4102', 'Montaj Yapıştırıcısı 290 g', 'Silikon ve Yapıştırıcılar', 'Tekno Yapı', '118.00', null, 'Adet', 220, '0.4', SaleMode::Cart, true],
        ['ORN-5001', 'Nitril Kaplı İş Eldiveni (12 Çift)', 'Eldivenler', 'Delta Pro', '210.00', null, 'Paket', 260, '1', SaleMode::Cart, false],
        ['ORN-5101', 'Baret, Ayarlı, Sarı', 'Koruyucu Ekipman', 'Delta Pro', '145.00', null, 'Adet', 140, '1', SaleMode::Cart, false],
        ['ORN-5102', 'Koruyucu Gözlük, Şeffaf', 'Koruyucu Ekipman', 'Delta Pro', '58.00', null, 'Adet', 320, '0.2', SaleMode::Cart, false],
    ];

    public function run(StockService $stock): void
    {
        $currencyId = Currency::query()->where('code', 'TRY')->value('id');
        $warehouse = Warehouse::query()->where('is_default', true)->first();

        $categories = $this->categories();
        $brands = $this->brands();

        foreach (self::PRODUCTS as $i => [$sku, $name, $category, $brand, $price, $comparePrice, $unit, $quantity, $desi, $saleMode, $isNew]) {
            if (Product::query()->where('sku', $sku)->exists()) {
                continue;
            }

            $product = Product::query()->create([
                'sku' => $sku,
                'name' => $name,
                'slug' => Str::slug($name),
                'brand_id' => $brands[$brand],
                'category_id' => $categories[$category],
                'short_description' => __('Örnek ürün: fiyat ve stok bilgileri denemek içindir.'),
                'price' => $price,
                'compare_price' => $comparePrice,
                'currency_id' => $currencyId,
                'tax_rate' => 20,
                'sale_mode' => $saleMode,
                'track_stock' => $saleMode === SaleMode::Cart,
                'min_stock' => $saleMode === SaleMode::Cart ? 10 : 0,
                'desi' => $desi,
                'unit' => $unit,
                'is_new' => $isNew,
                'status' => Status::Active,
                'sort_order' => $i + 1,
            ]);

            if ($warehouse !== null && $quantity > 0) {
                $stock->change($product, $warehouse, $quantity, StockMovementType::In, __('Örnek veri: açılış stoğu'));
            }
        }
    }

    /**
     * @return array<string, string> alt kategori adı => id
     */
    private function categories(): array
    {
        $ids = [];
        $order = 0;

        foreach (self::CATEGORIES as $parentName => $children) {
            $parent = Category::query()->firstOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'status' => Status::Active, 'sort_order' => ++$order],
            );

            foreach ($children as $j => $childName) {
                $ids[$childName] = Category::query()->firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'status' => Status::Active, 'sort_order' => $j + 1],
                )->id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, string> marka adı => id
     */
    private function brands(): array
    {
        $ids = [];

        foreach (self::BRANDS as $i => $name) {
            $ids[$name] = Brand::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => Status::Active, 'sort_order' => $i + 1],
            )->id;
        }

        return $ids;
    }
}
