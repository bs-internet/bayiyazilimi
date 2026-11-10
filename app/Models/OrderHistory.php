<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Shop\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'status',
    'note',
    'notify_customer',
    'user_id',
])]
class OrderHistory extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'notify_customer' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
