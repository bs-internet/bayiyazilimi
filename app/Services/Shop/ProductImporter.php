<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Media;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\HtmlSanitizer;
use App\Support\Money;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Manager\SharedStringsCaching\CachingStrategyFactoryInterface;
use OpenSpout\Reader\XLSX\Manager\SharedStringsCaching\CachingStrategyInterface;
use OpenSpout\Reader\XLSX\Manager\SharedStringsCaching\InMemoryStrategy;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Ürün Excel'ini (ProductSheet şablonu) içe aktarır.
 *
 * - Ürünler "Stok Kodu" ile eşleşir; yoksa yeni ürün eklenir.
 * - Kısmi güncelleme: dosyada olmayan sütunlara dokunulmaz.
 * - Boş hücre metin ve Evet/Hayır alanlarını temizler; Ürün Adı, Fiyat, Para Birimi,
 *   KDV, Satış Biçimi, Durum ve Birim boşsa mevcut değer korunur.
 * - "Stok: {depo kodu}" sütunları o deponun stoğunu eşitler (boş hücre dokunmaz).
 * - Görseller yalnızca görseli olmayan ürünlere eklenir: images_dir/{Stok Kodu}/
 *   klasöründen (yerel) ya da download_images açıksa "Görsel 1..5" bağlantılarından.
 */
class ProductImporter
{
    /** Bağlantıdan indirilen görselin üst sınırı (10 MB). */
    private const MAX_IMAGE_BYTES = 10_485_760;

    /** @var array<string, Category> path => category */
    private array $categoryCache = [];

    /** @var array<string, Brand> */
    private array $brandCache = [];

    /** @var array<string, string> */
    private array $unitCache = [];

    /** @var array<string, Currency|null> */
    private array $currencyCache = [];

    /** @var array<string, true> */
    private array $usedSlugs = [];

    private ?BigDecimal $defaultTaxRate = null;

    /** @var callable|null */
    private $progress = null;

    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function onProgress(callable $callback): self
    {
        $this->progress = $callback;

        return $this;
    }

    /**
     * @param  array{images_dir?: ?string, update_existing?: bool, update_stock?: bool, download_images?: bool, causer?: ?User, limit?: ?int}  $options
     * @return array{created: int, updated: int, skipped: int, images: int, errors: array<int, string>}
     */
    public function import(string $xlsxPath, array $options = []): array
    {
        $options += [
            'images_dir' => null,
            'update_existing' => true,
            'update_stock' => true,
            'download_images' => false,
            'causer' => null,
            'limit' => null,
        ];

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'images' => 0, 'errors' => []];

        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $this->usedSlugs = Product::withTrashed()->pluck('slug')->flip()->map(fn () => true)->all();

        // Paylaşılan metinleri bellekte tut: dosya tabanlı önbellek satır başına ~0,25 sn yavaşlatıyor.
        $reader = new Reader(null, new class implements CachingStrategyFactoryInterface
        {
            public function createBestCachingStrategy(?int $sharedStringsUniqueCount, string $tempFolder): CachingStrategyInterface
            {
                return new InMemoryStrategy($sharedStringsUniqueCount ?? 100000);
            }
        });
        $reader->open($xlsxPath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $columns = null;
                $line = 0;
                $index = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    $line++;

                    if ($columns === null) {
                        $columns = $this->columns($values, $result['errors']);

                        if ($columns === null) {
                            break 2;
                        }

                        continue;
                    }

                    $data = [];
                    foreach ($columns as $i => $field) {
                        $data[$field] = $this->cell($values[$i] ?? null);
                    }

                    $sku = trim((string) ($data['sku'] ?? ''));

                    if ($sku === '') {
                        if (array_filter($data, fn ($v) => $v !== null && $v !== '') !== []) {
                            $result['skipped']++;
                            $result['errors'][] = __('Satır :line: Stok Kodu boş, satır atlandı.', ['line' => $line]);
                        }

                        continue;
                    }

                    try {
                        $outcome = $this->importRow($sku, $data, $options);
                        $result[$outcome['action']]++;
                        $result['images'] += $outcome['images'];
                    } catch (Throwable $e) {
                        $result['errors'][] = __('Satır :line (:sku): :error', ['line' => $line, 'sku' => $sku, 'error' => $e->getMessage()]);
                    }

                    $index++;

                    if ($this->progress !== null) {
                        ($this->progress)($index, $sku);
                    }

                    if ($options['limit'] !== null && $index >= $options['limit']) {
                        break 2;
                    }
                }

                // Yalnızca ilk sayfa ürün listesidir ("Açıklamalar" sayfası okunmaz).
                break;
            }
        } finally {
            $reader->close();
        }

        if ($options['causer'] instanceof User) {
            CatalogChangedEvent::dispatch(
                null,
                'product',
                'imported',
                "Excel'den ürün içe aktardı (yeni: {$result['created']}, güncellenen: {$result['updated']}, hata: ".count($result['errors']).').',
                $options['causer'],
            );
        }

        return $result;
    }

    /**
     * Başlık satırı → sütun sırası => alan ("stock:{depo id}" depo stokları için).
     * Tanınmayan başlıklar ve bilinmeyen depolar uyarı olarak eklenir.
     *
     * @param  array<int, mixed>  $headers
     * @param  array<int, string>  $errors
     * @return array<int, string>|null
     */
    private function columns(array $headers, array &$errors): ?array
    {
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        if (! ProductSheet::isNative($headers)) {
            $errors[] = (string) __('Dosya biçimi tanınmadı: "Stok Kodu" sütunu bulunamadı. Lütfen içe aktarma ekranındaki şablonu kullanın.');

            return null;
        }

        $fields = ProductSheet::fieldsByHeader();
        $warehouses = Warehouse::query()->get(['id', 'code'])->keyBy(fn (Warehouse $w) => mb_strtoupper($w->code));
        $columns = [];
        $unknown = [];

        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }

            $code = ProductSheet::stockCode($header);

            if ($code !== null) {
                $warehouse = $warehouses->get(mb_strtoupper($code));

                if ($warehouse === null) {
                    $errors[] = (string) __('":header" sütunu atlandı: :code kodlu depo yok (Stok › Depolar).', ['header' => $header, 'code' => $code]);
                } else {
                    $columns[$i] = 'stock:'.$warehouse->id;
                }

                continue;
            }

            $field = $fields[ProductSheet::normalize($header)] ?? null;

            if ($field === null) {
                $unknown[] = $header;
            } elseif (! in_array($field, $columns, true)) {
                $columns[$i] = $field;
            }
        }

        if ($unknown !== []) {
            $errors[] = (string) __('Tanınmayan sütunlar atlandı: :columns.', ['columns' => implode(', ', $unknown)]);
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $options
     * @return array{action: 'created'|'updated'|'skipped', images: int}
     */
    private function importRow(string $sku, array $row, array $options): array
    {
        $product = Product::withTrashed()->where('sku', $sku)->first();

        // Silinmiş ürünün stok kodu dosyada yeniden geçiyorsa ürün geri getirilir ve
        // dosyadaki bilgilerle yeni ürün gibi kurulur (stok kodu tekil olduğu için
        // aynı kodla ikinci ürün açılamaz).
        $restoring = $product?->trashed() ?? false;

        if ($product !== null && ! $restoring && ! $options['update_existing']) {
            return ['action' => 'skipped', 'images' => 0];
        }

        // Değerler işlem dışında doğrulanır; hatalı satır hiçbir şey yazmaz.
        $attributes = $this->attributes($row, $restoring ? null : $product);

        return DB::transaction(function () use ($row, $options, $product, $sku, $attributes, $restoring) {
            $isNew = $product === null || $restoring;

            if ($restoring) {
                $product->restore();
                $product->fill($attributes)->save();
            } elseif ($product === null) {
                $attributes['sku'] = $sku;
                $attributes['slug'] = $this->uniqueSlug($this->text($row, 'slug') ?? (string) $attributes['name']);
                $product = Product::create($attributes);
            } else {
                $product->fill($attributes)->save();
            }

            if ($options['update_stock'] || $isNew) {
                $this->importStock($product, $row);
            }

            $images = 0;
            if (! $product->images()->exists()) {
                $images = $this->importImages($product, $row, $options['images_dir'], (bool) $options['download_images']);
            }

            return ['action' => $isNew ? 'created' : 'updated', 'images' => $images];
        });
    }

    /**
     * Satırdaki sütunlardan ürün alanları; dosyada olmayan sütunlar dahil edilmez.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributes(array $row, ?Product $product): array
    {
        $isNew = $product === null;
        $has = fn (string $field) => array_key_exists($field, $row);
        $a = [];

        $name = $this->text($row, 'name');
        if ($name !== null) {
            $a['name'] = Str::limit($name, 250, '');
        } elseif ($isNew) {
            throw new RuntimeException(__('Ürün Adı boş.'));
        }

        foreach (['model_number', 'barcode', 'meta_title', 'meta_description'] as $field) {
            if ($has($field)) {
                $a[$field] = $this->text($row, $field);
            }
        }

        if ($has('search_keywords')) {
            $keywords = $this->text($row, 'search_keywords');
            $a['search_keywords'] = $keywords !== null ? Str::limit($keywords, 2000, '') : null;
        }

        if ($has('notice')) {
            $notice = $this->text($row, 'notice');
            $a['notice'] = $notice !== null ? Str::limit(trim(strip_tags($notice)), 480, '') : null;
        }

        if ($has('description')) {
            $a['description'] = $this->cleanDescription((string) ($row['description'] ?? ''));
        }

        if ($has('brand')) {
            $a['brand_id'] = $this->brand((string) $this->text($row, 'brand'))?->id;
        }

        if ($has('category')) {
            $path = $this->text($row, 'category');
            $a['category_id'] = $path !== null ? $this->categoryPath($path)?->id : null;
        }

        if ($this->text($row, 'price') !== null) {
            $a['price'] = $this->decimal($row['price'], 'Fiyat');
        }

        if ($has('compare_price')) {
            $compare = $this->text($row, 'compare_price') !== null ? $this->decimal($row['compare_price'], 'İndirimsiz Fiyat') : null;
            $price = Money::decimal($a['price'] ?? $product?->price ?? 0);
            $a['compare_price'] = $compare !== null && $compare->isGreaterThan($price) ? $compare : null;
        }

        $currency = $this->text($row, 'currency');
        if ($currency !== null || $isNew) {
            $a['currency_id'] = $this->currency($currency ?? 'TRY')->id;
        }

        $tax = $this->text($row, 'tax_rate');
        if ($tax !== null) {
            $a['tax_rate'] = $this->percent($row['tax_rate']);
        } elseif ($isNew) {
            $a['tax_rate'] = $this->defaultTaxRate();
        }

        $saleMode = $this->text($row, 'sale_mode');
        if ($saleMode !== null) {
            $a['sale_mode'] = $this->saleMode($saleMode);
        } elseif ($isNew) {
            $a['sale_mode'] = Money::decimal($a['price'] ?? 0)->isPositive() ? SaleMode::Cart : SaleMode::Quote;
        }

        $status = $this->text($row, 'status');
        if ($status !== null) {
            $a['status'] = $this->choice($status, 'Durum', ['aktif' => true, 'pasif' => false]) ? Status::Active : Status::Passive;
        } elseif ($isNew) {
            $a['status'] = Status::Active;
        }

        foreach (['is_new' => 'Yeni Ürün', 'free_shipping' => 'Ücretsiz Kargo'] as $field => $label) {
            if ($has($field)) {
                $value = $this->text($row, $field);
                $a[$field] = $value !== null && $this->choice($value, $label, []);
            }
        }

        $unit = $this->text($row, 'unit');
        if ($unit !== null || $isNew) {
            $a['unit'] = $this->unit($unit ?? 'Adet');
        }

        if ($has('min_qty')) {
            $a['min_qty'] = max(1, $this->integer($row['min_qty'], 'Min. Sipariş', 1));
        }

        if ($has('max_qty')) {
            $max = $this->integer($row['max_qty'], 'Maks. Sipariş', 0);
            $a['max_qty'] = $max > 0 ? $max : null;
        }

        if ($has('delivery_days')) {
            $a['delivery_days'] = max(0, $this->integer($row['delivery_days'], 'Teslim Süresi', 0));
        }

        // Boş hücre ürünün eşiğini kaldırır (genel eşik geçerli olur).
        if ($has('min_stock')) {
            $a['min_stock'] = $this->text($row, 'min_stock') !== null ? max(0, $this->integer($row['min_stock'], 'Kritik Stok', 0)) : null;
        }

        if ($has('desi')) {
            $a['desi'] = $this->text($row, 'desi') !== null ? Money::max(0, $this->decimal($row['desi'], 'Desi')) : 0;
        }

        // Öne çıkanlar: yalnız dosyadaki sıralar değişir, diğerleri korunur.
        $present = array_values(array_filter(ProductSheet::HIGHLIGHT_FIELDS, $has));
        if ($present !== []) {
            $highlights = array_pad(array_values($product?->highlights ?? []), 3, null);

            foreach (ProductSheet::HIGHLIGHT_FIELDS as $i => $field) {
                if ($has($field)) {
                    $value = $this->text($row, $field);
                    $highlights[$i] = $value !== null ? Str::limit(strip_tags($value), 250, '') : null;
                }
            }

            $highlights = array_values(array_filter($highlights, fn ($v) => $v !== null && $v !== ''));
            $a['highlights'] = $highlights !== [] ? $highlights : null;
        }

        if ($isNew) {
            $a += ['price' => 0, 'track_stock' => true, 'allow_backorder' => false, 'min_qty' => 1];
        }

        return $a;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importStock(Product $product, array $row): void
    {
        // Lot / seri takipli üründe stok lot bilgisi olmadan girilemez (Mal Kabul kullanılır).
        if ($product->tracksLots()) {
            return;
        }

        $changed = false;

        foreach ($row as $field => $value) {
            if (! str_starts_with($field, 'stock:') || $this->text($row, $field) === null) {
                continue;
            }

            $warehouse = Warehouse::query()->findOrFail(substr($field, 6));
            $quantity = max(0, $this->integer($value, 'Stok: '.$warehouse->code, 0));
            $this->stock->setQuantity($product, $warehouse, $quantity, 'Excel içe aktarım', null, StockMovementType::Import);
            $changed = true;
        }

        if ($changed) {
            $product->syncStockTotal();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importImages(Product $product, array $row, ?string $imagesDir, bool $download): int
    {
        $sources = [];

        if ($imagesDir !== null) {
            $dir = rtrim($imagesDir, '/\\').DIRECTORY_SEPARATOR.$product->sku;

            if (is_dir($dir)) {
                $files = collect(File::files($dir))
                    ->filter(fn ($f) => in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true))
                    ->sortBy(fn ($f) => [(int) $f->getFilename(), $f->getFilename()])
                    ->values();

                foreach ($files as $file) {
                    $sources[] = ['local' => $file->getPathname(), 'name' => $file->getFilename()];
                }
            }
        }

        if ($sources === [] && $download) {
            foreach (ProductSheet::IMAGE_FIELDS as $field) {
                $url = (string) $this->text($row, $field);

                if (preg_match('#^https?://#i', $url)) {
                    $sources[] = ['remote' => $url, 'name' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'gorsel.jpg'];
                }
            }
        }

        $count = 0;
        $directory = 'media/products/'.Str::slug($product->sku);

        foreach ($sources as $i => $source) {
            $contents = isset($source['local'])
                ? @file_get_contents($source['local'])
                : $this->download($source['remote']);

            if ($contents === false || $contents === null || $contents === '') {
                continue;
            }

            $extension = strtolower(pathinfo($source['name'], PATHINFO_EXTENSION));
            $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'jpg';
            $filename = ($i + 1).'-'.(Str::limit(Str::slug(pathinfo($source['name'], PATHINFO_FILENAME)), 60, '') ?: 'gorsel').'.'.$extension;
            $path = $directory.'/'.$filename;

            Storage::disk('public')->put($path, $contents);

            Media::create([
                'mediable_type' => $product->getMorphClass(),
                'mediable_id' => $product->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $source['name'],
                'mime_type' => $this->mime($extension),
                'size' => strlen($contents),
                'collection' => Product::IMAGE_COLLECTION,
                'sort_order' => $i,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Excel'deki görsel bağlantısını indirir. Bağlantı dosyadan geldiği için iç ağ
     * adresleri (yönlendirmeyle gidilenler dahil) reddedilir; yalnız gerçek görsel kaydedilir.
     */
    private function download(string $url): ?string
    {
        if (! $this->isPublicUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(20)->withOptions(['allow_redirects' => [
                'max' => 3,
                'protocols' => ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri): void {
                    if (! $this->isPublicUrl((string) $uri)) {
                        throw new RuntimeException('İç ağ adresine yönlendirme.');
                    }
                },
            ]])->get($url);

            if (! $response->successful() || strlen($response->body()) > self::MAX_IMAGE_BYTES) {
                return null;
            }

            return @getimagesizefromstring($response->body()) !== false ? $response->body() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isPublicUrl(string $url): bool
    {
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return $host !== '';
    }

    private function mime(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private function categoryPath(string $path): ?Category
    {
        if (isset($this->categoryCache[$path])) {
            return $this->categoryCache[$path];
        }

        $parent = null;
        $built = [];

        foreach (array_map('trim', explode('>', $path)) as $segment) {
            if ($segment === '') {
                continue;
            }

            $built[] = $segment;
            $key = implode(' > ', $built);

            if (isset($this->categoryCache[$key])) {
                $parent = $this->categoryCache[$key];

                continue;
            }

            $category = Category::query()
                ->where('name', $segment)
                ->where('parent_id', $parent?->id)
                ->first();

            if ($category === null) {
                $category = Category::create([
                    'parent_id' => $parent?->id,
                    'name' => $segment,
                    'slug' => $this->uniqueCategorySlug($segment, $parent),
                    'status' => Status::Active,
                    'sort_order' => 0,
                ]);
            }

            $this->categoryCache[$key] = $category;
            $parent = $category;
        }

        if ($parent !== null) {
            $this->categoryCache[$path] = $parent;
        }

        return $parent;
    }

    private function uniqueCategorySlug(string $name, ?Category $parent): string
    {
        $base = Str::slug(str_replace('&', ' ve ', $name)) ?: 'kategori';
        $slug = $base;

        if (Category::query()->where('slug', $slug)->exists() && $parent !== null) {
            $slug = $parent->slug.'-'.$base;
        }

        $i = 2;
        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Birim Tanımlamalar → Birimler'de yoksa eklenir (büyük/küçük harf farkında
     * var olan adı kullanılır).
     */
    private function unit(string $name): string
    {
        $name = mb_substr(trim($name), 0, 32);

        if ($this->unitCache === []) {
            foreach (Unit::query()->pluck('name') as $existing) {
                $this->unitCache[mb_strtolower($existing)] = $existing;
            }
        }

        return $this->unitCache[mb_strtolower($name)] ??= Unit::query()->create([
            'name' => $name,
            'status' => Status::Active,
            'sort_order' => count($this->unitCache) + 1,
        ])->name;
    }

    private function brand(string $name): ?Brand
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        // Türkçe karakterlerde (Ç, İ…) SQLite lower() güvenilmez; karşılaştırma PHP'de yapılır.
        if ($this->brandCache === []) {
            foreach (Brand::query()->get() as $existing) {
                $this->brandCache[mb_strtolower($existing->name)] ??= $existing;
            }
        }

        return $this->brandCache[mb_strtolower($name)] ??= Brand::create([
            'name' => $name,
            'slug' => $this->uniqueBrandSlug($name),
            'status' => Status::Active,
        ]);
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'marka';
        $slug = $base;
        $i = 2;

        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Tanımlı para birimi (TL → TRY); tanımlı değilse satır hata verir.
     */
    private function currency(string $code): Currency
    {
        $code = mb_strtoupper(trim($code));
        $code = $code === 'TL' ? 'TRY' : $code;

        $currency = $this->currencyCache[$code] ??= Currency::query()->where('code', $code)->first();

        if ($currency === null) {
            throw new RuntimeException(__('Para Birimi ":code" tanımlı değil (Tanımlamalar › Para Birimleri).', ['code' => $code]));
        }

        return $currency;
    }

    private function defaultTaxRate(): BigDecimal
    {
        return $this->defaultTaxRate ??= Money::round(Tax::query()->whereKey(Setting::get('default_tax_id'))->value('rate') ?? 20);
    }

    private function saleMode(string $value): SaleMode
    {
        return match (mb_strtolower(str_replace('İ', 'i', trim($value)))) {
            'sepet', 'cart' => SaleMode::Cart,
            'teklif', 'quote' => SaleMode::Quote,
            default => throw new RuntimeException(__('Satış Biçimi ":value" geçersiz (Sepet ya da Teklif).', ['value' => $value])),
        };
    }

    /**
     * Evet/Hayır (ve verilen ek seçenekler) → bool; tanınmayan değer hata verir.
     *
     * @param  array<string, bool>  $extra
     */
    private function choice(string $value, string $label, array $extra): bool
    {
        $key = mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], trim($value)));
        $options = $extra + ['evet' => true, 'hayır' => false, 'e' => true, 'h' => false, '1' => true, '0' => false, 'true' => true, 'false' => false, 'x' => true];

        if (! array_key_exists($key, $options)) {
            $allowed = $extra !== [] ? implode(' / ', array_map('ucfirst', array_keys($extra))) : 'Evet / Hayır';

            throw new RuntimeException(__(':label ":value" geçersiz (:allowed).', ['label' => $label, 'value' => $value, 'allowed' => $allowed]));
        }

        return $options[$key];
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::limit(Str::slug($source), 180, '') ?: 'urun';
        $slug = $base;
        $i = 2;

        while (isset($this->usedSlugs[$slug])) {
            $slug = $base.'-'.$i++;
        }

        $this->usedSlugs[$slug] = true;

        return $slug;
    }

    private function cleanDescription(string $html): ?string
    {
        // Excel'in kaçışlı kontrol karakterleri (_x000D_ = CR, _x0009_ = TAB).
        $html = trim(str_replace(['_x000D_', '_x0009_', '_x000A_'], ['', ' ', "\n"], $html));

        if ($html === '') {
            return null;
        }

        $html = HtmlSanitizer::clean($html);

        return $html === '' ? null : $html;
    }

    /**
     * Hücre değerini sadeleştirir: tarih → metin, metin → kırpılmış.
     */
    private function cell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function text(array $row, string $field): ?string
    {
        $value = $row[$field] ?? null;

        if ($value === null || is_bool($value)) {
            return $value === true ? '1' : ($value === false ? '0' : null);
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Sayı: Excel sayısı ya da "1.249,90" / "1249.90" biçiminde metin.
     */
    private function decimal(mixed $value, string $label): BigDecimal
    {
        if (is_int($value) || is_float($value)) {
            return Money::decimal($value)->strippedOfTrailingZeros();
        }

        $text = str_replace([' ', '₺', '%'], '', trim((string) $value));

        if (str_contains($text, ',')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        }

        if (! is_numeric($text)) {
            throw new RuntimeException(__(':label ":value" sayı değil.', ['label' => $label, 'value' => $value]));
        }

        return Money::decimal($text);
    }

    private function integer(mixed $value, string $label, int $empty): int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $empty;
        }

        return Money::round($this->decimal($value, $label), 0)->toInt();
    }

    /**
     * KDV: 20 ya da Excel yüzde biçimli hücre (0,2) → 20.
     */
    private function percent(mixed $value): BigDecimal
    {
        $rate = $this->decimal($value, 'KDV');
        $rate = $rate->isPositive() && $rate->isLessThan(1) ? $rate->multipliedBy(100) : $rate;

        if ($rate->isNegative() || $rate->isGreaterThan(100)) {
            throw new RuntimeException(__('KDV ":value" geçersiz (0–100).', ['value' => $value]));
        }

        return Money::round($rate);
    }
}
