<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Status;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property BigDecimal $exchange_rate
 * @property RateSource $rate_source
 */
#[Fillable([
    'code',
    'name',
    'symbol',
    'decimal_places',
    'thousands_separator',
    'decimal_separator',
    'exchange_rate',
    'rate_source',
    'rate_updated_at',
    'status',
    'sort_order',
])]
class Currency extends Model
{
    use HasFactory, HasSortOrder, HasStatus, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'exchange_rate' => Decimal::class.':6',
            'rate_source' => RateSource::class,
            'rate_updated_at' => 'immutable_datetime',
            'status' => Status::class,
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper($value);
    }

    /**
     * Tutarı bu para biriminin ayraçlarıyla biçimlendirir (sembol dahil).
     */
    public function format(BigNumber|float|int|string|null $amount): string
    {
        // Float'a çevirmeden: kesin ondalık, HalfUp, ayraçlar elle (büyük tutarlarda da tam).
        $value = Money::round($amount, max(0, (int) $this->decimal_places));
        $negative = $value->isNegative();
        [$whole, $fraction] = array_pad(explode('.', (string) $value->abs()), 2, '');
        $whole = strrev(implode((string) $this->thousands_separator, str_split(strrev($whole), 3)));
        $formatted = ($negative ? '-' : '').$whole.($fraction !== '' ? $this->decimal_separator.$fraction : '');

        return $this->code === 'TRY'
            ? $formatted.' '.$this->symbol
            : $this->symbol.$formatted;
    }
}
