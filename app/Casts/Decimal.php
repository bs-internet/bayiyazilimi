<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Para / oran sütunları için kesin ondalık cast'i: okurken `Brick\Math\BigDecimal`
 * (sütunun hanesiyle), yazarken sabit haneli metin. Float hiç oluşmaz; JSON'a
 * (Inertia, ERP) metin olarak çıkar ("1234.50").
 *
 * Kullanım: `'grand_total' => Decimal::class.':2'`, birim fiyat `:4`, kur `:6`.
 *
 * @implements CastsAttributes<BigDecimal|null, BigNumber|int|float|string|null>
 */
final class Decimal implements CastsAttributes
{
    private readonly int $scale;

    public function __construct(int|string $scale = 2)
    {
        $this->scale = (int) $scale;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::decimal(is_float($value) || is_int($value) || is_string($value) ? $value : (string) $value)
            ->toScale($this->scale, RoundingMode::HalfUp);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::str($value instanceof BigNumber || is_int($value) || is_float($value) ? $value : (string) $value, $this->scale);
    }
}
