<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Hesabın fatura türü: bireysel (TC kimlik no) ya da kurumsal (vergi dairesi + vergi no).
 */
enum BillingType: string
{
    case Individual = 'individual';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::Individual => __('Bireysel'),
            self::Corporate => __('Kurumsal'),
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
