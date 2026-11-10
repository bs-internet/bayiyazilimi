<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Enums\Shop\RefundStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sağlayıcı üzerinden yapılan (kısmi ya da tam) iade.
 *
 * @property BigDecimal $amount
 * @property RefundStatus $status
 */
#[Fillable([
    'payment_id',
    'amount',
    'currency_code',
    'status',
    'failed_reason',
    'user_id',
    'completed_at',
])]
class PaymentRefund extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => Decimal::class.':2',
            'status' => RefundStatus::class,
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
