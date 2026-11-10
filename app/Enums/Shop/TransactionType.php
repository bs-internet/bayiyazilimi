<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Cari hareket türü.
 */
enum TransactionType: string
{
    case Order = 'order';
    case Payment = 'payment';
    case ReturnCredit = 'return';
    case Refund = 'refund';
    case Opening = 'opening';
    case Adjustment = 'adjustment';
    case ChequeBounce = 'cheque_bounce';

    public function label(): string
    {
        return match ($this) {
            self::Order => __('Satış'),
            self::Payment => __('Tahsilat'),
            self::ReturnCredit => __('İade'),
            self::Refund => __('Para iadesi'),
            self::Opening => __('Açılış bakiyesi'),
            self::Adjustment => __('Dekont'),
            self::ChequeBounce => __('Karşılıksız çek / senet'),
        };
    }
}
