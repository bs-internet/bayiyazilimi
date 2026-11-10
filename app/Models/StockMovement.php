<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Shop\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'product_id',
    'warehouse_id',
    'warehouse_location_id',
    'stock_lot_id',
    'type',
    'quantity',
    'balance_after',
    'reference_type',
    'reference_id',
    'note',
    'user_id',
])]
class StockMovement extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
