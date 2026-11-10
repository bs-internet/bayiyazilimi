<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Çek / senet durumu. Karşılıksız ve iade cariye borç yazar.
 */
enum ChequeStatus: string
{
    case Portfolio = 'portfolio';
    case Deposited = 'deposited';
    case Collected = 'collected';
    case Bounced = 'bounced';
    case Returned = 'returned';
    case Endorsed = 'endorsed';

    public function label(): string
    {
        return match ($this) {
            self::Portfolio => __('Portföyde'),
            self::Deposited => __('Tahsile verildi'),
            self::Collected => __('Tahsil edildi'),
            self::Bounced => __('Karşılıksız'),
            self::Returned => __('İade edildi'),
            self::Endorsed => __('Ciro edildi'),
        };
    }

    /**
     * Bu durumdan geçilebilecek durumlar.
     *
     * @return array<int, self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Portfolio => [self::Deposited, self::Endorsed, self::Collected, self::Returned, self::Bounced],
            self::Deposited => [self::Collected, self::Bounced, self::Portfolio],
            self::Endorsed => [self::Bounced],
            self::Collected, self::Bounced, self::Returned => [],
        };
    }

    /** Cariye borç yazan (alacağı geri alan) durumlar. */
    public function reversesCredit(): bool
    {
        return in_array($this, [self::Bounced, self::Returned], true);
    }

    /** Riske eklenen (henüz kesin tahsil edilmemiş) durumlar. */
    public static function atRisk(): array
    {
        return [self::Portfolio->value, self::Deposited->value, self::Endorsed->value];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
