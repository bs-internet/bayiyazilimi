<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Status;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property ?BigDecimal $rate
 */
#[Fillable([
    'name',
    'rate',
    'status',
    'sort_order',
])]
class Tax extends Model
{
    use HasFactory, HasSortOrder, HasStatus, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'rate' => Decimal::class.':2',
            'status' => Status::class,
        ];
    }
}
