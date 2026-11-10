<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_count_id',
    'product_id',
    'warehouse_location_id',
    'stock_lot_id',
    'expected',
    'counted',
    'user_id',
])]
class StockCountLine extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'expected' => 'integer',
            'counted' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
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

    public function difference(): int
    {
        return $this->counted - $this->expected;
    }
}
