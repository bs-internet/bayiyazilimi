<?php

declare(strict_types=1);

namespace App\Enums\Shop;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Sale = 'sale';
    case Return = 'return';
    case Import = 'import';
    /** Aynı depoda lokasyon değişimi (depo toplamı değişmez). */
    case Relocate = 'relocate';
    /** Siparişte düşülen lotun / seri numarasının değiştirilmesi (sipariş toplamı değişmez). */
    case LotChange = 'lot_change';

    public function label(): string
    {
        return match ($this) {
            self::LotChange => __('Lot Değişimi'),
            self::In => __('Giriş'),
            self::Out => __('Çıkış'),
            self::Adjust => __('Sayım Düzeltme'),
            self::TransferIn => __('Transfer (Giriş)'),
            self::TransferOut => __('Transfer (Çıkış)'),
            self::Sale => __('Satış'),
            self::Return => __('İade / İptal'),
            self::Import => __('İçe Aktarım'),
            self::Relocate => __('Lokasyon Değişimi'),
        };
    }
}
