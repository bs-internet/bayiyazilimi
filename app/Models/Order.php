<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Support\Payments\PaymentMethods;
use App\Support\Payments\PaymentStart;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property BigDecimal $exchange_rate
 * @property BigDecimal $base_exchange_rate
 * @property BigDecimal $payment_adjustment
 * @property BigDecimal $subtotal
 * @property BigDecimal $tax_total
 * @property BigDecimal $shipping_total
 * @property BigDecimal $total_desi
 * @property BigDecimal $discount_total
 * @property BigDecimal $grand_total
 *
 * Durum enum'ları (cast).
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 */
#[Fillable([
    'number',
    'status',
    'payment_status',
    'payment_method',
    'user_id',
    'account_id',
    'quote_id',
    'campaign_id',
    'campaign_name',
    'warehouse_id',
    'carrier_id',
    'total_desi',
    'customer_name',
    'email',
    'phone',
    'billing_address',
    'shipping_address',
    'currency_code',
    'exchange_rate',
    'base_exchange_rate',
    'subtotal',
    'tax_total',
    'shipping_total',
    'discount_total',
    'payment_adjustment',
    'payment_adjustment_label',
    'grand_total',
    'customer_note',
    'admin_note',
    'cargo_company',
    'tracking_number',
    'stock_deducted',
    'paid_at',
    'due_date',
    'shipped_at',
    'cancelled_at',
    'ip_address',
    'invoice_number',
    'invoiced_at',
])]
class Order extends Model
{
    use BelongsToAccount, HasUuids;

    /**
     * Sipariş oluşturulurken ödeme sürücüsünün isteği (kaydedilmez): müşteri
     * sipariş sayfasına mı, bir ödeme sayfasına mı gider.
     */
    public ?PaymentStart $paymentStart = null;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'exchange_rate' => Decimal::class.':6',
            'base_exchange_rate' => Decimal::class.':6',
            'payment_adjustment' => Decimal::class.':2',
            'subtotal' => Decimal::class.':2',
            'tax_total' => Decimal::class.':2',
            'shipping_total' => Decimal::class.':2',
            'total_desi' => Decimal::class.':2',
            'discount_total' => Decimal::class.':2',
            'grand_total' => Decimal::class.':2',
            'stock_deducted' => 'boolean',
            'paid_at' => 'immutable_datetime',
            'due_date' => 'immutable_date',
            'shipped_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'invoiced_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class)->latest();
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function paymentMethodLabel(): string
    {
        return PaymentMethods::label($this->payment_method);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function currency(): ?Currency
    {
        return Currency::query()->where('code', $this->currency_code)->first();
    }

    /**
     * Müşterinin siparişi giriş yapmadan görebileceği imzalı bağlantı. Yalnız
     * vitrin modülü kuruluysa vardır (`shop.order.public_url`); yoksa null.
     */
    public function publicUrl(): ?string
    {
        return hooks()->apply('shop.order.public_url', null, order: $this);
    }

    /**
     * Müşterinin e-postadaki bağlantısı: bayi → /app (vitrine hiç uğramaz). Bayi
     * olmayan müşteri (vitrin) için bağlantıyı vitrin modülü verir
     * (`shop.order.customer_url`: Hesabım ya da imzalı sayfa).
     */
    public function customerUrl(): string
    {
        $app = route('app.shop.orders.show', $this->id);

        if ($this->account_id !== null && $this->account?->isDealer()) {
            return $app;
        }

        return hooks()->apply('shop.order.customer_url', null, order: $this) ?? $app;
    }
}
