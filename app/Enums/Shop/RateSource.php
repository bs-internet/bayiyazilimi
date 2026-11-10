<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum RateSource: string
{
    case Manual = 'manual';
    case Tcmb = 'tcmb';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Elle'),
            self::Tcmb => __('TCMB'),
        };
    }
}
