<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Vadeli siparişte kredi limiti aşılınca ya da vadesi geçmiş borç varken ne olur.
 */
enum LimitAction: string
{
    /** Sipariş "Onay Bekliyor"a düşer; yönetici onaylar ya da reddeder. */
    case Approval = 'approval';
    /** Sipariş verilemez. */
    case Block = 'block';
    /** Engel yok (yalnız vadesi geçmiş borç için). */
    case Allow = 'allow';

    public function label(): string
    {
        return match ($this) {
            self::Approval => __('Onaya düşsün'),
            self::Block => __('Engellensin'),
            self::Allow => __('Serbest'),
        };
    }

    /**
     * @param  array<int, self>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(array $cases = [self::Approval, self::Block]): array
    {
        return array_map(fn (self $a) => ['value' => $a->value, 'label' => $a->label()], $cases);
    }
}
