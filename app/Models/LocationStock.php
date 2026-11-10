<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir ürünün bir depo lokasyonundaki (lot takipliyse o lottaki) miktarı. Depo
 * toplamı (`product_stocks`) bu kayıtların toplamıdır; ikisini de yalnızca
 * StockService değiştirir.
 */
#[Fillable([
    'product_id',
    'warehouse_id',
    'warehouse_location_id',
    'stock_lot_id',
    'quantity',
])]
class LocationStock extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    /** @return BelongsTo<StockLot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }
}
