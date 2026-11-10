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
 * @property BigDecimal $min_desi
 * @property BigDecimal $max_desi
 * @property BigDecimal $price
 */
#[Fillable([
    'carrier_id',
    'min_desi',
    'max_desi',
    'price',
])]
class CarrierRate extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'min_desi' => Decimal::class.':2',
            'max_desi' => Decimal::class.':2',
            'price' => Decimal::class.':2',
        ];
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }
}
