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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Kesin ondalık sütunlar (App\Casts\Decimal → Brick\Math\BigDecimal).
 *
 * @property BigDecimal $extra_desi_price
 */
#[Fillable([
    'code',
    'name',
    'logo',
    'tracking_url',
    'extra_desi_price',
    'prices_include_tax',
    'delivery_days',
    'is_default',
    'status',
    'sort_order',
])]
class Carrier extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    protected function casts(): array
    {
        return [
            'extra_desi_price' => Decimal::class.':2',
            'prices_include_tax' => 'boolean',
            'delivery_days' => 'integer',
            'is_default' => 'boolean',
            'status' => Status::class,
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CarrierRate::class)->orderBy('min_desi');
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return str_starts_with($this->logo, '/') || str_starts_with($this->logo, 'http')
            ? $this->logo
            : Storage::disk('public')->url($this->logo);
    }

    public function trackingLink(?string $code): ?string
    {
        if (! $this->tracking_url || ! $code) {
            return null;
        }

        return str_replace('{code}', urlencode($code), $this->tracking_url);
    }
}
