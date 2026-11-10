<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property BigDecimal $unit_price
 * @property BigDecimal $discount_amount
 * @property BigDecimal $tax_rate
 * @property BigDecimal $tax_amount
 * @property BigDecimal $line_total
 */
#[Fillable([
    'order_id',
    'product_id',
    'sku',
    'name',
    'quantity',
    'unit_price',
    'discount_amount',
    'campaign',
    'tax_rate',
    'tax_amount',
    'line_total',
])]
class OrderItem extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => Decimal::class.':4',
            'discount_amount' => Decimal::class.':2',
            'tax_rate' => Decimal::class.':2',
            'tax_amount' => Decimal::class.':2',
            'line_total' => Decimal::class.':2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
