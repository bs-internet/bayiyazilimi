<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Enums\Shop\ReturnReason;
use App\Enums\Shop\ReturnStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property ?BigDecimal $refund_amount
 * @property ReturnStatus $status
 */
#[Fillable([
    'number',
    'order_id',
    'account_id',
    'user_id',
    'status',
    'reason',
    'customer_note',
    'admin_note',
    'refund_amount',
    'restocked',
    'resolved_at',
    'resolved_by',
])]
class ReturnRequest extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'refund_amount' => Decimal::class.':2',
            'restocked' => 'boolean',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<ReturnRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
