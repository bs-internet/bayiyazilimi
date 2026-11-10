<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\HasMedia;
use App\Enums\BillingType;
use App\Enums\Shop\CustomerType;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property ?BigDecimal $discount_rate
 * @property ?BigDecimal $credit_limit
 * @property ?CustomerType $type
 */
#[Fillable([
    'code',
    'title',
    'billing_type',
    'tax_office',
    'tax_number',
    'identity_number',
    'invoice_email',
    'type',
    'dealer_group_id',
    'warehouse_id',
    'discount_rate',
    'payment_terms',
    'credit_limit',
    'payment_term_days',
    'over_limit_action',
    'overdue_action',
    'allowed_payment_methods',
])]
class Account extends Model
{
    use HasFactory, HasMedia, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'billing_type' => BillingType::class,
            'discount_rate' => Decimal::class.':2',
            'credit_limit' => Decimal::class.':2',
            'payment_term_days' => 'integer',
            'allowed_payment_methods' => 'array',
        ];
    }

    /**
     * Geçerli cari çalışma şekli (bayiye özel → grup → varsayılan).
     */
    public function creditTerms(): CreditTerms
    {
        return CreditTerms::for($this);
    }

    /** Cari hareketler. */
    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function dealerGroup(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class);
    }

    /** Bayinin bölge deposu (yalnız bayi hesaplarında). */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Stok gösterimi ve sipariş düşümünde öncelikli depo: bayiye atanmış, aktif
     * ve satılabilir depo. Yoksa null (tüm satılabilir depolar geçerli).
     */
    public function regionWarehouse(): ?Warehouse
    {
        $warehouse = $this->isDealer() ? $this->warehouse : null;

        return $warehouse !== null && $warehouse->isActive() && $warehouse->is_sellable ? $warehouse : null;
    }

    public function isDealer(): bool
    {
        return $this->type === CustomerType::Dealer;
    }

    /**
     * Bayinin indirim yüzdesi: bayiye özel oran girilmişse o, yoksa grubun oranı.
     * Müşteri hesapları için 0.
     */
    public function dealerDiscountRate(): BigDecimal
    {
        if (! $this->isDealer()) {
            return Money::round(0);
        }

        return Money::max(0, Money::min(100, $this->discount_rate ?? $this->dealerGroup?->discount_rate ?? 0));
    }

    /**
     * Member media lives directly under the account code on each disk
     * (e.g. public/{code}, private/{code}).
     */
    protected function mediaAccountCode(): ?string
    {
        return (string) $this->code;
    }
}
