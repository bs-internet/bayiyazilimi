<?php

declare(strict_types=1);

namespace App\Enums\Shop;

use App\Services\Shop\ShopSettings;

/**
 * Yöneticinin elle girdiği tahsilat türleri (cari alacak). Havale / EFT
 * tahsilatı `bank_transfer` sürücü anahtarıyla yazılır.
 */
enum CollectionType: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Pos = 'pos';
    case Offset = 'offset';
    case Cheque = 'cheque';
    case PromissoryNote = 'promissory_note';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Nakit'),
            self::BankTransfer => __('Havale / EFT'),
            self::Pos => __('POS (Kart)'),
            self::Offset => __('Mahsup / Virman'),
            self::Cheque => __('Çek'),
            self::PromissoryNote => __('Senet'),
        };
    }

    public function isInstrument(): bool
    {
        return in_array($this, [self::Cheque, self::PromissoryNote], true);
    }

    /**
     * Tahsilat formunun seçenekleri; çek / senet yalnız Cari Ayarları'nda açıksa.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        $cheques = ShopSettings::bool('cheques_enabled');

        return array_values(array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label()],
            array_filter(self::cases(), fn (self $t) => $cheques || ! $t->isInstrument()),
        ));
    }
}
