<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Support\Money;
use Brick\Math\BigNumber;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Koşul mantıkları ve karşılaştırma yardımcıları.
 */
final class Operators
{
    public const EQ = 'eq';

    public const NEQ = 'neq';

    public const IN = 'in';

    public const NOT_IN = 'not_in';

    public const GTE = 'gte';

    public const LTE = 'lte';

    /** @return array<string, string> */
    public static function equality(): array
    {
        return [self::EQ => __('eşittir'), self::NEQ => __('eşit değildir')];
    }

    /** @return array<string, string> */
    public static function membership(): array
    {
        return [self::IN => __('şunlardan biri'), self::NOT_IN => __('hiçbiri değil')];
    }

    /** @return array<string, string> */
    public static function numeric(): array
    {
        return [self::GTE => __('en az'), self::LTE => __('en fazla')];
    }

    public static function compare(BigNumber|int $actual, string $operator, mixed $value): bool
    {
        // Kesin karşılaştırma (tutar / adet); sayı değilse 0.
        $actual = Money::decimal($actual);
        $target = Money::decimal($value instanceof BigNumber || is_numeric($value) ? ($value instanceof BigNumber ? $value : (string) $value) : 0);

        return match ($operator) {
            self::GTE => $actual->isGreaterThanOrEqualTo($target),
            self::LTE => $actual->isLessThanOrEqualTo($target),
            self::EQ => $actual->isEqualTo($target),
            self::NEQ => ! $actual->isEqualTo($target),
            default => false,
        };
    }

    public static function is(?string $actual, string $operator, mixed $value): bool
    {
        $equal = $actual !== null && $actual === (string) $value;

        return match ($operator) {
            self::EQ => $equal,
            self::NEQ => ! $equal,
            default => false,
        };
    }

    /**
     * @param  array<int, string>|string|null  $actual  değerlerden biri listede mi (ör. ürünün kategori + üst kategorileri)
     */
    public static function within(array|string|null $actual, string $operator, mixed $value): bool
    {
        $list = array_map('strval', (array) $value);
        $hit = array_intersect(array_map('strval', (array) $actual), $list) !== [];

        return match ($operator) {
            self::IN => $hit,
            self::NOT_IN => ! $hit,
            default => false,
        };
    }

    /**
     * within()'in SQL karşılığı: sütun listede mi (NOT_IN'de boş sütun da uyar).
     *
     * @param  array<int, string>|null  $list  verilmezse `value`
     */
    public static function constrainIn(Builder $query, string $column, string $operator, mixed $value, ?array $list = null): void
    {
        $list ??= array_values(array_map('strval', (array) $value));

        match ($operator) {
            self::IN => $list === [] ? $query->whereRaw('1 = 0') : $query->whereIn($column, $list),
            self::NOT_IN => $list === [] ? null : $query->where(fn (Builder $q) => $q->whereNotIn($column, $list)->orWhereNull($column)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
