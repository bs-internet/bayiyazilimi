<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RefundStatus;
use App\Support\Money;
use App\Support\Payments\PaymentMethods;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Bir ödeme / tahsilat: kart (sağlayıcı), onaylanan havale, havale bildirimi ya
 * da elle girilen tahsilat. `order_id` boşsa siparişten bağımsız cari tahsilattır.
 * "Ödendi" olduğunda cariye alacak yazılır (LedgerService).
 *
 * @property BigDecimal $amount
 * @property BigDecimal $fee_amount
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $payload
 */
#[Fillable([
    'account_id',
    'order_id',
    'method',
    'provider',
    'merchant_oid',
    'amount',
    'currency_code',
    'installment',
    'fee_amount',
    'status',
    'failed_reason',
    'note',
    'payload',
    'user_id',
    'paid_at',
])]
class Payment extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => Decimal::class.':2',
            'fee_amount' => Decimal::class.':2',
            'installment' => 'integer',
            'status' => PaymentStatus::class,
            'payload' => 'array',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PaymentRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class)->latest();
    }

    /**
     * İade edilmiş ya da işlemdeki iadelerin toplamı (iade edilebilir tutardan düşülür).
     */
    public function refundedAmount(): BigDecimal
    {
        $amounts = PaymentRefund::query()->where('payment_id', $this->id)
            ->whereIn('status', array_map(fn (RefundStatus $s) => $s->value, array_filter(RefundStatus::cases(), fn (RefundStatus $s) => $s->reservesAmount())))
            ->pluck('amount')
            ->all();

        return Money::add(...$amounts);
    }

    public function methodLabel(): string
    {
        return PaymentMethods::label($this->method);
    }

    public function currency(): ?Currency
    {
        return Currency::withTrashed()->where('code', $this->currency_code)->first();
    }

    /**
     * Tekil ödeme referansı (sağlayıcıya giden sipariş no): harf + rakam, en fazla 64.
     */
    public static function newReference(string $prefix = 'P'): string
    {
        do {
            $ref = $prefix.now()->format('ymdHis').Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('merchant_oid', $ref)->exists());

        return $ref;
    }
}
