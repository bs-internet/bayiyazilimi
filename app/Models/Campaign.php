<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Status;
use App\Services\Shop\CurrencyManager;
use App\Support\Shop\Campaigns\CampaignEngine;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kampanya: "Kime / ne zaman" koşul grupları, "Hangi ürünlere" koşulları ve
 * bir işlem. Koşul ve işlem türleri CampaignRegistry'dedir.
 *
 * @property string $id
 * @property string $name
 * @property string|null $badge
 * @property array<int, array<int, array{field: string, operator: string, value: mixed}>>|null $audience_rules
 * @property string $product_scope all | match
 * @property array<int, array{field: string, operator: string, value: mixed}>|null $product_rules
 * @property string $action
 * @property array<string, mixed>|null $action_settings
 * @property string $shipping_mode cart (sepete göre) | free | fixed
 * @property string|null $shipping_amount sabit kargo ücreti (KDV dahil, varsayılan para biriminde)
 * @property string|null $currency_code işlem tutarlarının (tutar indirimi) para birimi; boşsa varsayılan
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property Status $status
 * @property ?BigDecimal $shipping_amount
 * @property int|null $usage_limit toplam kullanım (sipariş) sınırı
 * @property int|null $usage_limit_per_customer müşteri (hesap / üye / e-posta) başına sınır
 * @property ?BigDecimal $budget toplam kazanç tavanı (ana para biriminde)
 */
#[Fillable([
    'name',
    'badge',
    'audience_rules',
    'product_scope',
    'product_rules',
    'action',
    'action_settings',
    'shipping_mode',
    'shipping_amount',
    'currency_code',
    'starts_at',
    'ends_at',
    'usage_limit',
    'usage_limit_per_customer',
    'budget',
    'status',
    'sort_order',
])]
class Campaign extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    public const SCOPE_ALL = 'all';

    public const SCOPE_MATCH = 'match';

    public const SHIPPING_CART = 'cart';

    public const SHIPPING_FREE = 'free';

    public const SHIPPING_FIXED = 'fixed';

    protected $attributes = [
        'product_scope' => self::SCOPE_ALL,
        'shipping_mode' => self::SHIPPING_CART,
        'status' => 'passive',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'audience_rules' => 'array',
            'product_rules' => 'array',
            'action_settings' => 'array',
            'shipping_amount' => Decimal::class.':2',
            'budget' => Decimal::class.':2',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => Status::class,
        ];
    }

    protected static function booted(): void
    {
        // İstek içi canlı kampanya önbelleği değişiklikte tazelenir.
        static::saved(fn () => app(CampaignEngine::class)->flush());
        static::deleted(fn () => app(CampaignEngine::class)->flush());
    }

    /**
     * Aktif ve tarih aralığı içinde olanlar.
     */
    #[Scope]
    protected function live(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query->where('status', Status::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    /**
     * Bu kampanyayla birlikte uygulanabilen kampanyalar (ilişki simetrik saklanır).
     *
     * @return BelongsToMany<Campaign, $this>
     */
    public function combinables(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'campaign_combinations', 'campaign_id', 'other_id');
    }

    /**
     * İki kampanya aynı sepette birlikte uygulanabilir mi.
     */
    public function combinesWith(self $other): bool
    {
        return $this->relationLoaded('combinables')
            ? $this->combinables->contains('id', $other->id)
            : $this->combinables()->whereKey($other->id)->exists();
    }

    /**
     * @return HasMany<CampaignUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CampaignUsage::class);
    }

    public function hasLimits(): bool
    {
        return $this->usage_limit !== null || $this->usage_limit_per_customer !== null || $this->budget !== null;
    }

    public function isLiveAt(CarbonImmutable $at): bool
    {
        return $this->isActive()
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($at));
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        // İşlemler tutarları kampanyanın para biriminde okur (`currency`: kod ya da null = varsayılan).
        return [...($this->action_settings ?? []), 'currency' => $this->currency_code];
    }

    /**
     * Tutarların para birimi: seçilen (aktif) para birimi, yoksa varsayılan.
     */
    public static function moneyCurrency(?string $code): Currency
    {
        $currencies = app(CurrencyManager::class);

        return ($code ? Currency::query()->where('code', $code)->first() : null) ?? $currencies->base();
    }
}
