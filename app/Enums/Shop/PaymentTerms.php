<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Bayinin cari çalışma şekli.
 */
enum PaymentTerms: string
{
    /** Sipariş ödeme gelince işlenir (kart / havale). */
    case Prepaid = 'prepaid';
    /** Limit dahilinde vadeli sipariş; vadesi gelince ödenir. */
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Prepaid => __('Peşin'),
            self::Credit => __('Vadeli (açık hesap)'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
