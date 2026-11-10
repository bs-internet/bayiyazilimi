<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Borç (müşterinin borcu artar) / alacak (müşterinin borcu azalır).
 */
enum TransactionDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => __('Borç'),
            self::Credit => __('Alacak'),
        };
    }

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }

    /** Bakiyeye etkisi: borç +, alacak −. */
    public function sign(): int
    {
        return $this === self::Debit ? 1 : -1;
    }
}
