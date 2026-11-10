<?php

declare(strict_types=1);

namespace App\Enums\Shop;

/**
 * Sağlayıcı üzerinden iadenin durumu: kayıt sağlayıcı çağrısından önce
 * "İşlemde" yazılır; sağlayıcı sonucu "Tamamlandı" ya da "Başarısız" yapar.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('İşlemde'),
            self::Succeeded => __('Tamamlandı'),
            self::Failed => __('Başarısız'),
        };
    }

    /** İade edilebilir tutardan düşülür mi (işlemdeki iade de ayrılmış sayılır). */
    public function reservesAmount(): bool
    {
        return $this !== self::Failed;
    }
}
