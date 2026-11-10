<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Ürünün izlenebilirlik türü: stok lot ya da seri numarası bazında tutulur.
 */
enum StockTracking: string
{
    case None = 'none';
    case Lot = 'lot';
    case Serial = 'serial';

    public function label(): string
    {
        return match ($this) {
            self::None => __('Takip yok'),
            self::Lot => __('Lot / parti'),
            self::Serial => __('Seri numarası'),
        };
    }

    public function tracksLots(): bool
    {
        return $this !== self::None;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
