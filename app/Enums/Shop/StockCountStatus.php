<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum StockCountStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Sayılıyor'),
            self::Completed => __('Onaylandı'),
            self::Cancelled => __('İptal Edildi'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
