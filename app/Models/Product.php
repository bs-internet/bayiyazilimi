<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\HasMedia;
use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Events\Shop\StockLowEvent;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property BigDecimal $price
 * @property ?BigDecimal $compare_price
 * @property BigDecimal $tax_rate
 * @property BigDecimal $desi
 * @property StockTracking $tracking
 */
#[Fillable([
    'sku',
    'barcode',
    'model_number',
    'name',
    'slug',
    'brand_id',
    'category_id',
    'short_description',
    'description',
    'notice',
    'highlights',
    'price',
    'compare_price',
    'currency_id',
    'tax_rate',
    'sale_mode',
    'track_stock',
    'allow_backorder',
    'tracking',
    'expiry_tracking',
    'stock',
    'min_stock',
    'min_qty',
    'max_qty',
    'delivery_days',
    'desi',
    'unit',
    'is_new',
    'free_shipping',
    'meta_title',
    'meta_description',
    'search_keywords',
    'status',
    'sort_order',
])]
class Product extends Model
{
    use HasMedia, HasSortOrder, HasStatus, HasUuids, SoftDeletes;

    public const IMAGE_COLLECTION = 'images';

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'price' => Decimal::class.':4',
            'compare_price' => Decimal::class.':4',
            'tax_rate' => Decimal::class.':2',
            'sale_mode' => SaleMode::class,
            'track_stock' => 'boolean',
            'allow_backorder' => 'boolean',
            'tracking' => StockTracking::class,
            'expiry_tracking' => 'boolean',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'low_stock_notified_at' => 'immutable_datetime',
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'delivery_days' => 'integer',
            'desi' => Decimal::class.':2',
            'is_new' => 'boolean',
            'free_shipping' => 'boolean',
            'status' => Status::class,
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class)->withTrashed();
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /** @return HasMany<LocationStock, $this> */
    public function locationStocks(): HasMany
    {
        return $this->hasMany(LocationStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    /**
     * Etkin izlenebilirlik: özellik Stok Ayarları'ndan kapalıysa her ürün takipsizdir.
     */
    public function trackingMode(): StockTracking
    {
        return ShopSettings::traceability() ? ($this->tracking ?? StockTracking::None) : StockTracking::None;
    }

    /**
     * SKT takibi etkin mi (özellik açık ve ürün lot / seri takipli)?
     */
    public function tracksExpiry(): bool
    {
        return $this->tracksLots() && (bool) $this->expiry_tracking;
    }

    /**
     * Stok lot ya da seri numarası bazında mı tutuluyor?
     */
    public function tracksLots(): bool
    {
        return $this->trackingMode()->tracksLots();
    }

    public function tracksSerials(): bool
    {
        return $this->trackingMode() === StockTracking::Serial;
    }

    /** @return HasMany<Media, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(Media::class, 'mediable_id')
            ->where('mediable_type', $this->getMorphClass())
            ->where('collection', self::IMAGE_COLLECTION)
            ->orderBy('sort_order');
    }

    /**
     * İsteği yapanın görebileceği ürünler: aktif olanlar (sepet, teklif listesi,
     * hızlı sipariş ortak). Modüller ek kısıt koyabilir (`shop.products.visible`;
     * ör. vitrin: "yalnız bayilere" ürünleri ziyaretçiden gizler).
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), Status::Active->value)
            // Pasif kategori / markanın ürünleri de görünmez (kategori yoksa serbest).
            ->whereDoesntHave('category', fn (Builder $q) => $q->where('status', Status::Passive->value))
            ->whereDoesntHave('brand', fn (Builder $q) => $q->where('status', Status::Passive->value));

        hooks()->do('shop.products.visible', query: $query);
    }

    /**
     * "Yeni ürünler" listesi (is_new): Excel'in IsNew kolonu ve ana sayfadaki
     * "Yeni ürünler" şeridi aynı listeyi düzenler. Sıra: şeritte verilen sıra;
     * o sırada olmayanlar (ör. Excel'den yeni gelenler) en yeniden başlayarak başa.
     *
     * @param  array<int, string>  $order  şeritte kayıtlı sıra
     * @return array<int, string>
     */
    public static function newListIds(array $order = []): array
    {
        $ids = static::query()->where('is_new', true)->orderByDesc('created_at')->pluck('id')->all();
        $known = array_values(array_intersect($order, $ids));

        return [...array_values(array_diff($ids, $known)), ...$known];
    }

    /**
     * İsteği yapan kullanıcı bayi mi? (panel/konsol bağlamında false)
     */
    public static function viewerIsDealer(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->account_id === null) {
            return false;
        }

        return $user->loadMissing('account')->isDealer();
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term, $query): void {
            foreach (preg_split('/\s+/', $term) ?: [] as $word) {
                $like = '%'.$word.'%';
                $q->where(fn (Builder $w) => $w
                    ->where($query->qualifyColumn('name'), 'like', $like)
                    ->orWhere($query->qualifyColumn('sku'), 'like', $like)
                    ->orWhere($query->qualifyColumn('model_number'), 'like', $like)
                    ->orWhere($query->qualifyColumn('barcode'), 'like', $like)
                    ->orWhere($query->qualifyColumn('search_keywords'), 'like', $like));
            }
        });
    }

    public function coverUrl(): string
    {
        $media = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();

        return $media?->url() ?? asset('storefront/img/no-image.svg');
    }

    /**
     * @return array<int, string>
     */
    public function imageUrls(): array
    {
        $urls = $this->images->map(fn (Media $m) => $m->url())->filter()->values()->all();

        return $urls !== [] ? $urls : [asset('storefront/img/no-image.svg')];
    }

    public function isPurchasable(): bool
    {
        return $this->sale_mode->allowsCart()
            && $this->price->isPositive()
            && $this->hasStockFor($this->min_qty);
    }

    /**
     * Vitrinde fiyat gösterilir mi? Teklif ürünlerinde fiyat gizlidir.
     */
    public function showsPrice(): bool
    {
        return $this->sale_mode->allowsCart() && $this->price->isPositive();
    }

    public function isQuotable(): bool
    {
        return $this->sale_mode->allowsQuote() || ! $this->isPurchasable();
    }

    public function hasStockFor(int $quantity): bool
    {
        return ! $this->track_stock || $this->allow_backorder || $this->stock >= $quantity;
    }

    public function inStock(): bool
    {
        return ! $this->track_stock || $this->stock > 0;
    }

    public function discountRate(): int
    {
        $compare = $this->compare_price;
        $price = $this->price;

        if ($compare === null || ! $compare->isPositive() || ! $price->isPositive() || $compare->isLessThanOrEqualTo($price)) {
            return 0;
        }

        // Gösterim yüzdesi (tam sayı): (1 − fiyat / liste) × 100.
        return Money::sub(100, Money::div($price->multipliedBy(100), $compare, 4))->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /**
     * product_stocks tablosundan satılabilir depoların toplamını `stock` kolonuna yazar.
     */
    public function syncStockTotal(): void
    {
        $before = (int) $this->stock;

        $sellable = fn (Builder $q) => $q->where('is_sellable', true)->where('status', Status::Active->value);

        $total = (int) $this->stocks()->whereHas('warehouse', $sellable)->sum('quantity');

        // Süresi geçmiş lotlar satılamaz; satılabilir toplamdan düşülür (gece
        // `shop:refresh-expired-stock` süresi yeni dolanlar için yeniden hesaplar).
        $total -= ! ShopSettings::traceability() ? 0 : (int) $this->locationStocks()
            ->where('quantity', '>', 0)
            ->whereHas('warehouse', $sellable)
            ->whereHas('lot', fn (Builder $q) => $q->whereDate('expires_at', '<', CarbonImmutable::today()))
            ->sum('quantity');

        $this->forceFill(['stock' => $total])->saveQuietly();

        $this->evaluateLowStock($before);
    }

    /**
     * Stok takipli ve satılabilir stoğu kritik eşiğe (ürüne özel, yoksa genel) inmiş ürünler.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->where($this->qualifyColumn('track_stock'), true)
            ->whereRaw(
                "{$this->qualifyColumn('stock')} <= coalesce({$this->qualifyColumn('min_stock')}, ?)",
                [ShopSettings::int('low_stock_threshold')],
            );
    }

    /**
     * Kritik stok eşiği: ürüne özel değer, yoksa Stok Ayarları'ndaki genel eşik.
     */
    public function lowStockThreshold(): int
    {
        return $this->min_stock ?? ShopSettings::int('low_stock_threshold');
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock <= $this->lowStockThreshold();
    }

    /**
     * Stok kritik eşiğe (ya da altına) **inerek** geldiğinde bir kez bildirim
     * olayı çıkarır; eşiğin üstüne çıkınca işaret sıfırlanır ve sonraki
     * düşüşte yeniden bildirilir. Artışla eşik altında kalmak (ilk giriş,
     * içe aktarım) bildirim üretmez.
     */
    private function evaluateLowStock(int $before): void
    {
        $low = $this->isLowStock();

        if (! $low) {
            if ($this->low_stock_notified_at !== null) {
                $this->forceFill(['low_stock_notified_at' => null])->saveQuietly();
            }

            return;
        }

        if ($this->stock >= $before || $this->low_stock_notified_at !== null || ! $this->isActive()) {
            return;
        }

        $this->forceFill(['low_stock_notified_at' => now()])->saveQuietly();

        StockLowEvent::dispatch($this);
    }
}
