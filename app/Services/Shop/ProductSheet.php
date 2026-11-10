<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Warehouse;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ürün Excel şablonunun tek tanımı: içe aktarım (ProductImporter), dışa aktarım
 * (ProductExporter) ve panelden indirilen boş şablon aynı sütunları kullanır.
 *
 * İlk sayfa ürün listesidir (ilk satır başlık). Başlıklar büyük/küçük harf, "*" ve
 * parantez içi açıklamadan bağımsız eşleşir ("KDV (%)" = "kdv"). Depo stokları
 * "Stok: {depo kodu}" başlıklı sütunlardadır. İkinci sayfa ("Açıklamalar") sütun
 * rehberidir; içe aktarımda okunmaz.
 */
final class ProductSheet
{
    public const STOCK_PREFIX = 'Stok: ';

    public const HELP_SHEET = 'Açıklamalar';

    /**
     * Alan => [başlık, zorunlu mu, açıklama, örnek].
     *
     * @var array<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    public const COLUMNS = [
        'sku' => ['Stok Kodu', true, 'Ürünün benzersiz kodu. Ürünler bu kodla eşleşir: sistemde varsa güncellenir, yoksa yeni ürün eklenir.', 'ABC-100'],
        'name' => ['Ürün Adı', true, 'Yeni ürün için zorunlu; mevcut üründe boş bırakılırsa ad değişmez.', 'Örnek Ürün 5 L'],
        'brand' => ['Marka', false, 'Marka adı; sistemde yoksa oluşturulur. Boş hücre markayı kaldırır.', 'Örnek Marka'],
        'category' => ['Kategori', false, 'Kategorinin tam yolu, alt kategoriler ">" ile ayrılır. Yoksa oluşturulur. Boş hücre kategoriyi kaldırır.', 'Temizlik > Deterjanlar'],
        'model_number' => ['Model No', false, 'Üretici model numarası.', 'MX-500'],
        'barcode' => ['Barkod', false, 'EAN / barkod numarası.', '8690000000001'],
        'price' => ['Fiyat', false, 'KDV hariç satış fiyatı (ondalık için virgül ya da nokta). Yeni üründe boş ya da 0 ise ürün "Teklif" ile satılır. Mevcut üründe boş bırakılırsa fiyat değişmez.', '149,90'],
        'compare_price' => ['İndirimsiz Fiyat', false, 'KDV hariç üstü çizili fiyat; Fiyat\'tan büyükse indirim olarak gösterilir. Boş hücre indirimi kaldırır.', '179,90'],
        'currency' => ['Para Birimi', false, 'Fiyatın para birimi kodu: TRY, USD, EUR … (Tanımlamalar › Para Birimleri\'nde tanımlı olmalı). Yeni üründe boşsa TRY.', 'TRY'],
        'tax_rate' => ['KDV (%)', false, 'KDV oranı yüzde olarak: 20, 10, 1, 0. Yeni üründe boşsa varsayılan KDV oranı.', '20'],
        'sale_mode' => ['Satış Biçimi', false, '"Sepet": fiyat görünür, sepete eklenir. "Teklif": fiyat gizlenir, teklif istenir. Yeni üründe boşsa fiyata göre belirlenir.', 'Sepet'],
        'status' => ['Durum', false, '"Aktif" ya da "Pasif". Pasif ürün sitede görünmez. Yeni üründe boşsa Aktif.', 'Aktif'],
        'is_new' => ['Yeni Ürün', false, '"Evet" ise "Yeni ürünler" listesinde gösterilir. Evet / Hayır.', 'Hayır'],
        'free_shipping' => ['Ücretsiz Kargo', false, 'Evet / Hayır.', 'Hayır'],
        'unit' => ['Birim', false, 'Satış birimi: Adet, Koli, Kg … Birimler\'de yoksa eklenir. Yeni üründe boşsa Adet.', 'Adet'],
        'min_qty' => ['Min. Sipariş', false, 'En az sipariş adedi. Boşsa 1.', '1'],
        'max_qty' => ['Maks. Sipariş', false, 'En çok sipariş adedi. Boşsa sınır yok.', ''],
        'delivery_days' => ['Teslim Süresi (Gün)', false, 'Kargoya veriliş süresi (gün).', '2'],
        'min_stock' => ['Kritik Stok', false, 'Ürünün kritik stok eşiği: toplam stok bu sayıya inince bildirim gelir. Boşsa Stok Ayarları\'ndaki genel eşik kullanılır; boş hücre ürünün eşiğini kaldırır.', '10'],
        'desi' => ['Desi', false, 'Kargo desisi (hacimsel ağırlık).', '1,5'],
        'highlight_1' => ['Öne Çıkan 1', false, 'Ürün sayfasında fiyatın yanında listelenen kısa özellik.', 'Konsantre formül'],
        'highlight_2' => ['Öne Çıkan 2', false, 'Kısa özellik.', ''],
        'highlight_3' => ['Öne Çıkan 3', false, 'Kısa özellik.', ''],
        'notice' => ['Uyarı Notu', false, 'Ürün sayfasında gösterilen kısa not (en çok 480 karakter).', ''],
        'description' => ['Açıklama', false, 'Ürün açıklaması; HTML kullanılabilir.', '<p>Ürün açıklaması…</p>'],
        'slug' => ['URL', false, 'Ürün adresinin son kısmı. Yalnız yeni üründe kullanılır; boşsa ürün adından üretilir.', 'ornek-urun-5-l'],
        'meta_title' => ['SEO Başlık', false, 'Arama motorları için sayfa başlığı. Boşsa ürün adı kullanılır.', ''],
        'meta_description' => ['SEO Açıklama', false, 'Arama motorları için kısa açıklama.', ''],
        'search_keywords' => ['Arama Kelimeleri', false, 'Site içi aramada eşleşecek ek kelimeler.', 'deterjan sıvı'],
        'image_1' => ['Görsel 1', false, 'Görselin tam adresi (https://…). Yalnızca görseli olmayan ürünlere ve "görselleri indir" seçiliyse eklenir.', 'https://ornek.com/gorsel.jpg'],
        'image_2' => ['Görsel 2', false, 'Görselin tam adresi.', ''],
        'image_3' => ['Görsel 3', false, 'Görselin tam adresi.', ''],
        'image_4' => ['Görsel 4', false, 'Görselin tam adresi.', ''],
        'image_5' => ['Görsel 5', false, 'Görselin tam adresi.', ''],
    ];

    public const HIGHLIGHT_FIELDS = ['highlight_1', 'highlight_2', 'highlight_3'];

    public const IMAGE_FIELDS = ['image_1', 'image_2', 'image_3', 'image_4', 'image_5'];

    /**
     * Başlık satırı: zorunlu sütunlar "*" ile, ardından her depo için "Stok: KOD".
     *
     * @param  Collection<int, Warehouse>  $warehouses
     * @return list<string>
     */
    public static function headers(Collection $warehouses): array
    {
        $headers = array_values(array_map(
            fn (array $column) => $column[1] ? $column[0].'*' : $column[0],
            self::COLUMNS,
        ));

        foreach ($warehouses as $warehouse) {
            $headers[] = self::STOCK_PREFIX.$warehouse->code;
        }

        return $headers;
    }

    /**
     * Başlık metnini karşılaştırılabilir biçime getirir: "KDV (%)*" → "kdv".
     */
    public static function normalize(string $header): string
    {
        $header = str_replace(['I', 'İ', '*'], ['ı', 'i', ''], $header);
        $header = (string) preg_replace('/\(.*?\)/u', '', $header);
        $header = (string) preg_replace('/\s+/u', ' ', $header);

        return trim(mb_strtolower($header));
    }

    /**
     * Normalleştirilmiş başlık => alan eşlemesi.
     *
     * @return array<string, string>
     */
    public static function fieldsByHeader(): array
    {
        $map = [];

        foreach (self::COLUMNS as $field => [$header]) {
            $map[self::normalize($header)] = $field;
        }

        return $map;
    }

    /**
     * "Stok: DEPO" başlığından depo kodu; stok sütunu değilse null.
     */
    public static function stockCode(string $header): ?string
    {
        if (! preg_match('/^\s*stok\s*:\s*(.+)$/iu', $header, $match)) {
            return null;
        }

        $code = trim($match[1]);

        return $code !== '' ? $code : null;
    }

    /**
     * Dosyanın bizim şablonumuzda olup olmadığı ("Stok Kodu" sütunu var mı).
     *
     * @param  list<string>  $headers
     */
    public static function isNative(array $headers): bool
    {
        $sku = self::normalize(self::COLUMNS['sku'][0]);

        foreach ($headers as $header) {
            if (self::normalize($header) === $sku) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ürün sayfasının başlığını kalın yazar ve sütun genişliklerini ayarlar.
     *
     * @param  list<string>  $headers
     */
    public static function writeHeader(Writer $writer, array $headers): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Ürünler');
        $sheet->setColumnWidthForRange(18, 1, max(1, count($headers)));
        $sheet->setColumnWidth(36, 2);

        $writer->addRow(Row::fromValuesWithStyle($headers, (new Style)->withFontBold(true)));
    }

    /**
     * İkinci sayfa: sütun rehberi (içe aktarımda okunmaz).
     *
     * @param  Collection<int, Warehouse>  $warehouses
     */
    public static function writeHelpSheet(Writer $writer, Collection $warehouses): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(self::HELP_SHEET);
        $sheet->setColumnWidth(22, 1);
        $sheet->setColumnWidth(10, 2);
        $sheet->setColumnWidth(100, 3);
        $sheet->setColumnWidth(28, 4);

        $bold = (new Style)->withFontBold(true);
        $writer->addRow(Row::fromValuesWithStyle(['Sütun', 'Zorunlu', 'Açıklama', 'Örnek'], $bold));

        foreach (self::COLUMNS as [$header, $required, $help, $example]) {
            $writer->addRow(Row::fromValues([$header, $required ? 'Evet' : '', $help, $example]));
        }

        $codes = $warehouses->pluck('code')->implode(', ');
        $writer->addRow(Row::fromValues([
            self::STOCK_PREFIX.'{depo kodu}',
            '',
            'Her depo için bir sütun; hücre o depodaki stok adedidir. Boş hücre stoğu değiştirmez. Depo kodları Stok › Depolar\'dadır'.($codes !== '' ? " (şu an: {$codes})." : '.'),
            '25',
        ]));

        $writer->addRow(Row::fromValues([]));

        foreach ([
            'Ürünler "Stok Kodu" ile eşleşir; dosyada olmayan sütunlara dokunulmaz. Örneğin yalnız "Stok Kodu" ve "Fiyat" sütunlarıyla toplu fiyat güncellenebilir.',
            'Boş hücre metin alanlarını ve Evet/Hayır alanlarını temizler; Ürün Adı, Fiyat, Para Birimi, KDV, Satış Biçimi, Durum ve Birim boşsa değişmez.',
            'Yalnızca ilk sayfa ("Ürünler") okunur; ilk satır başlıktır. Sütun sırası önemli değildir.',
        ] as $note) {
            $writer->addRow(Row::fromValues(['Not', '', $note, '']));
        }
    }
}
