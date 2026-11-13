<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use App\Enums\Shop\OrderStatus;
use App\Support\Api\ApiAbilities;

/**
 * MCP araçları. Her araç bir REST API uç noktasına karşılık gelir: aynı
 * doğrulama, aynı yetki (API anahtarının yetkisi yoksa araç listelenmez ve
 * çağrılamaz), aynı yanıt. Araç çağrısı API'ye iç istek olarak gider
 * (McpController); böylece MCP ile API arasında davranış farkı olmaz.
 *
 * Eklentiler araç eklemek için `mcp.tools` filtresini kullanır (aynı biçim).
 */
final class McpTools
{
    /**
     * @return array<string, array{title: string, description: string, ability: ?string, readOnly: bool, schema: array<string, mixed>, request: callable(array<string, mixed>): array{0: string, 1: string, 2?: array<string, mixed>}}>
     */
    public static function all(): array
    {
        $string = fn (string $description, array $extra = []) => ['type' => 'string', 'description' => $description, ...$extra];
        $integer = fn (string $description, array $extra = []) => ['type' => 'integer', 'description' => $description, ...$extra];
        $number = fn (string $description) => ['type' => 'number', 'description' => $description];
        $object = fn (array $properties, array $required = []) => ['type' => 'object', 'properties' => (object) $properties, 'required' => $required, 'additionalProperties' => false];
        $paging = [
            'page' => $integer('Sayfa numarası (1’den başlar).', ['minimum' => 1]),
            'per_page' => $integer('Sayfa boyutu (en çok 100).', ['minimum' => 1, 'maximum' => 100]),
            'updated_since' => $string('Yalnız bu tarihten sonra değişenler (ISO-8601).'),
        ];
        $only = fn (array $args, array $keys) => array_filter(array_intersect_key($args, array_flip($keys)), fn ($v) => $v !== null && $v !== '');
        $address = $object([
            'name' => $string('Ad soyad / ünvan'),
            'address' => $string('Açık adres'),
            'city' => $string('İl'),
            'district' => $string('İlçe'),
            'postal_code' => $string('Posta kodu'),
            'phone' => $string('Telefon'),
        ], ['address']);

        $tools = [
            'get_api_info' => [
                'title' => 'API bilgisi',
                'description' => 'Sistemin adı, sürümü, ana para birimi ve bu anahtarın yetkileri.',
                'ability' => null, 'readOnly' => true,
                'schema' => $object([]),
                'request' => fn (array $a) => ['GET', 'v1'],
            ],
            'search_products' => [
                'title' => 'Ürün ara',
                'description' => 'Ürünleri ad, stok kodu ya da barkodla arar; fiyat (KDV hariç, ürünün para biriminde), KDV oranı ve depo bazında stokla döner.',
                'ability' => ApiAbilities::PRODUCTS_READ, 'readOnly' => true,
                'schema' => $object(['q' => $string('Ad, stok kodu ya da barkodda geçen metin'), 'status' => $string('active ya da passive', ['enum' => ['active', 'passive']]), ...$paging]),
                'request' => fn (array $a) => ['GET', 'v1/products', $only($a, ['q', 'status', 'page', 'per_page', 'updated_since'])],
            ],
            'get_product' => [
                'title' => 'Ürün',
                'description' => 'Stok koduyla tek ürün.',
                'ability' => ApiAbilities::PRODUCTS_READ, 'readOnly' => true,
                'schema' => $object(['sku' => $string('Stok kodu')], ['sku']),
                'request' => fn (array $a) => ['GET', 'v1/products/'.rawurlencode((string) ($a['sku'] ?? ''))],
            ],
            'create_product' => [
                'title' => 'Ürün ekle',
                'description' => 'Yeni ürün. Fiyat KDV hariçtir; kategori ve marka slug ile verilir. Stok bu araçla değil set_stock ile yazılır.',
                'ability' => ApiAbilities::PRODUCTS_WRITE, 'readOnly' => false,
                'schema' => $object([
                    'sku' => $string('Stok kodu (benzersiz)'), 'name' => $string('Ürün adı'), 'price' => $number('KDV hariç birim fiyat'),
                    'tax_rate' => $number('KDV oranı (%)'), 'currency' => $string('Para birimi kodu (boşsa ana para birimi)'),
                    'barcode' => $string('Barkod'), 'category' => $string('Kategori slug'), 'brand' => $string('Marka slug'),
                    'unit' => $string('Birim (ör. Adet)'), 'description' => $string('Açıklama'),
                    'status' => $string('active ya da passive', ['enum' => ['active', 'passive']]),
                    'sale_mode' => $string('cart (sepetten satış) ya da quote (yalnız teklif)', ['enum' => ['cart', 'quote']]),
                ], ['sku', 'name', 'price', 'tax_rate']),
                'request' => fn (array $a) => ['POST', 'v1/products', $a],
            ],
            'update_product' => [
                'title' => 'Ürün güncelle',
                'description' => 'Ürünün adını, barkodunu, fiyatını, KDV oranını, durumunu ya da sipariş adetlerini değiştirir; yalnız verilen alanlar değişir.',
                'ability' => ApiAbilities::PRODUCTS_WRITE, 'readOnly' => false,
                'schema' => $object([
                    'sku' => $string('Stok kodu'), 'name' => $string('Ürün adı'), 'barcode' => $string('Barkod'),
                    'price' => $number('KDV hariç birim fiyat'), 'compare_price' => $number('Karşılaştırma (üstü çizili) fiyat'),
                    'tax_rate' => $number('KDV oranı (%)'), 'status' => $string('active ya da passive', ['enum' => ['active', 'passive']]),
                    'min_qty' => $integer('En az sipariş adedi'), 'max_qty' => $integer('En çok sipariş adedi'),
                ], ['sku']),
                'request' => fn (array $a) => ['PATCH', 'v1/products/'.rawurlencode((string) ($a['sku'] ?? '')), array_diff_key($a, ['sku' => true])],
            ],
            'update_prices' => [
                'title' => 'Toplu fiyat güncelle',
                'description' => 'Birden çok ürünün KDV hariç fiyatını stok koduyla günceller (en çok 1.000 satır).',
                'ability' => ApiAbilities::PRODUCTS_WRITE, 'readOnly' => false,
                'schema' => $object(['items' => ['type' => 'array', 'description' => 'Satırlar', 'items' => $object(['sku' => $string('Stok kodu'), 'price' => $number('KDV hariç fiyat'), 'compare_price' => $number('Karşılaştırma fiyatı')], ['sku', 'price'])]], ['items']),
                'request' => fn (array $a) => ['PUT', 'v1/prices', $a],
            ],
            'get_stock' => [
                'title' => 'Stok sorgula',
                'description' => 'Depo bazında stok miktarları; stok kodu ya da depo koduyla süzülür.',
                'ability' => ApiAbilities::PRODUCTS_READ, 'readOnly' => true,
                'schema' => $object(['sku' => $string('Stok kodu'), 'warehouse' => $string('Depo kodu'), ...$paging]),
                'request' => fn (array $a) => ['GET', 'v1/stock', $only($a, ['sku', 'warehouse', 'page', 'per_page', 'updated_since'])],
            ],
            'set_stock' => [
                'title' => 'Stok yaz',
                'description' => 'Ürünlerin depo stoğunu mutlak miktar olarak yazar (fark düzeltme hareketi olur). Tümü ya da hiçbiri.',
                'ability' => ApiAbilities::STOCK_WRITE, 'readOnly' => false,
                'schema' => $object(['items' => ['type' => 'array', 'description' => 'Satırlar', 'items' => $object(['sku' => $string('Stok kodu'), 'warehouse' => $string('Depo kodu'), 'quantity' => $integer('Yeni miktar', ['minimum' => 0])], ['sku', 'warehouse', 'quantity'])]], ['items']),
                'request' => fn (array $a) => ['PUT', 'v1/stock', $a],
            ],
            'list_orders' => [
                'title' => 'Siparişleri listele',
                'description' => 'Siparişler (durum ve ödeme durumuna göre süzülür), kalemleri ve tutarlarıyla.',
                'ability' => ApiAbilities::ORDERS_READ, 'readOnly' => true,
                'schema' => $object([
                    'status' => $string('Sipariş durumu', ['enum' => array_column(OrderStatus::cases(), 'value')]),
                    'payment_status' => $string('Ödeme durumu (pending, paid, failed …)'),
                    ...$paging,
                ]),
                'request' => fn (array $a) => ['GET', 'v1/orders', $only($a, ['status', 'payment_status', 'page', 'per_page', 'updated_since'])],
            ],
            'get_order' => [
                'title' => 'Sipariş',
                'description' => 'Sipariş numarasıyla tek sipariş.',
                'ability' => ApiAbilities::ORDERS_READ, 'readOnly' => true,
                'schema' => $object(['number' => $string('Sipariş numarası')], ['number']),
                'request' => fn (array $a) => ['GET', 'v1/orders/'.rawurlencode((string) ($a['number'] ?? ''))],
            ],
            'create_order' => [
                'title' => 'Sipariş oluştur',
                'description' => 'Cari hesap adına sipariş açar. Birim fiyat (KDV dahil) verilmezse bayinin fiyatı uygulanır. Stok düşer; vadeli siparişte kredi kuralları geçerlidir.',
                'ability' => ApiAbilities::ORDERS_WRITE, 'readOnly' => false,
                'schema' => $object([
                    'account' => $string('Cari hesap kodu'),
                    'payment_method' => $string('on_account (cari / vadeli) ya da bank_transfer (havale)', ['enum' => ['on_account', 'bank_transfer']]),
                    'lines' => ['type' => 'array', 'description' => 'Kalemler', 'items' => $object(['sku' => $string('Stok kodu'), 'quantity' => $integer('Adet', ['minimum' => 1]), 'unit_price' => $number('KDV dahil birim fiyat (boşsa bayi fiyatı)')], ['sku', 'quantity'])],
                    'shipping' => $address,
                    'customer_note' => $string('Müşteri notu'),
                    'note' => $string('Sipariş geçmişine düşülecek not'),
                ], ['account', 'payment_method', 'lines']),
                'request' => fn (array $a) => ['POST', 'v1/orders', $a],
            ],
            'update_order_status' => [
                'title' => 'Sipariş durumu değiştir',
                'description' => 'Siparişin durumunu değiştirir (izinli geçişler panelle aynı). Kargo firması kodu ve takip numarası verilebilir.',
                'ability' => ApiAbilities::ORDERS_WRITE, 'readOnly' => false,
                'schema' => $object([
                    'number' => $string('Sipariş numarası'),
                    'status' => $string('Yeni durum', ['enum' => array_column(OrderStatus::cases(), 'value')]),
                    'note' => $string('Not'), 'notify' => ['type' => 'boolean', 'description' => 'Müşteriye bildirim gitsin mi'],
                    'carrier' => $string('Kargo firması kodu'), 'tracking_number' => $string('Kargo takip numarası'),
                ], ['number', 'status']),
                'request' => fn (array $a) => ['PATCH', 'v1/orders/'.rawurlencode((string) ($a['number'] ?? '')).'/status', array_diff_key($a, ['number' => true])],
            ],
            'list_accounts' => [
                'title' => 'Cari hesapları listele',
                'description' => 'Bayi / müşteri hesapları, güncel bakiyeleriyle (pozitif = müşteri borçlu).',
                'ability' => ApiAbilities::ACCOUNTS_READ, 'readOnly' => true,
                'schema' => $object(['type' => $string('dealer ya da customer', ['enum' => ['dealer', 'customer']]), ...$paging]),
                'request' => fn (array $a) => ['GET', 'v1/accounts', $only($a, ['type', 'page', 'per_page', 'updated_since'])],
            ],
            'get_account' => [
                'title' => 'Cari hesap',
                'description' => 'Hesap koduyla tek hesap: bakiye, kredi limiti, kullanılabilir limit, vadesi geçmiş borç.',
                'ability' => ApiAbilities::ACCOUNTS_READ, 'readOnly' => true,
                'schema' => $object(['code' => $string('Cari hesap kodu')], ['code']),
                'request' => fn (array $a) => ['GET', 'v1/accounts/'.rawurlencode((string) ($a['code'] ?? ''))],
            ],
            'list_account_transactions' => [
                'title' => 'Cari hareketler',
                'description' => 'Hesabın cari hareketleri (en yeni önce), tarih aralığıyla süzülür.',
                'ability' => ApiAbilities::ACCOUNTS_READ, 'readOnly' => true,
                'schema' => $object(['code' => $string('Cari hesap kodu'), 'from' => $string('Başlangıç tarihi (YYYY-AA-GG)'), 'to' => $string('Bitiş tarihi (YYYY-AA-GG)'), 'page' => $integer('Sayfa'), 'per_page' => $integer('Sayfa boyutu')], ['code']),
                'request' => fn (array $a) => ['GET', 'v1/accounts/'.rawurlencode((string) ($a['code'] ?? '')).'/transactions', $only($a, ['from', 'to', 'page', 'per_page'])],
            ],
            'record_account_transaction' => [
                'title' => 'Cari hareket kaydet',
                'description' => 'Dış sistemden cari hareket: collection (tahsilat), debit (borç dekontu) ya da credit (alacak dekontu). Aynı referans tekrar gönderilirse aynı kayıt döner.',
                'ability' => ApiAbilities::LEDGER_WRITE, 'readOnly' => false,
                'schema' => $object([
                    'code' => $string('Cari hesap kodu'),
                    'type' => $string('Hareket türü', ['enum' => ['collection', 'debit', 'credit']]),
                    'amount' => $number('Tutar (ana para birimi)'), 'reference' => $string('Dış sistemdeki belge / referans no'),
                    'date' => $string('İşlem tarihi (YYYY-AA-GG)'), 'description' => $string('Açıklama'),
                ], ['code', 'type', 'amount', 'reference']),
                'request' => fn (array $a) => ['POST', 'v1/accounts/'.rawurlencode((string) ($a['code'] ?? '')).'/transactions', array_diff_key($a, ['code' => true])],
            ],
            'list_categories' => [
                'title' => 'Kategoriler',
                'description' => 'Ürün kategorileri (slug, üst kategori).',
                'ability' => ApiAbilities::PRODUCTS_READ, 'readOnly' => true,
                'schema' => $object($paging),
                'request' => fn (array $a) => ['GET', 'v1/categories', $only($a, ['page', 'per_page', 'updated_since'])],
            ],
            'list_brands' => [
                'title' => 'Markalar',
                'description' => 'Markalar (slug).',
                'ability' => ApiAbilities::PRODUCTS_READ, 'readOnly' => true,
                'schema' => $object($paging),
                'request' => fn (array $a) => ['GET', 'v1/brands', $only($a, ['page', 'per_page', 'updated_since'])],
            ],
            'list_warehouses' => [
                'title' => 'Depolar',
                'description' => 'Depolar (kod, satışa açık mı).',
                'ability' => ApiAbilities::PRODUCTS_READ, 'readOnly' => true,
                'schema' => $object([]),
                'request' => fn (array $a) => ['GET', 'v1/warehouses'],
            ],
        ];

        $extra = hooks()->apply('mcp.tools', []);

        return [...$tools, ...(is_array($extra) ? $extra : [])];
    }
}
