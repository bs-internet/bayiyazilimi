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
 * @property BigDecimal $list_price
 * @property ?BigDecimal $unit_price
 * @property BigDecimal $tax_rate
 * @property BigDecimal $line_total
 */
#[Fillable([
    'quote_id',
    'product_id',
    'sku',
    'name',
    'unit',
    'quantity',
    'list_price',
    'unit_price',
    'tax_rate',
    'line_total',
    'note',
    'sort_order',
])]
class QuoteItem extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
            'list_price' => Decimal::class.':4',
            'unit_price' => Decimal::class.':4',
            'tax_rate' => Decimal::class.':2',
            'line_total' => Decimal::class.':2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
