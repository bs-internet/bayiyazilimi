<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sağlayıcı üzerinden iade yapılamadı: sağlayıcı hata verdi ya da "İşlemde"
 * kalmış iadeyi yönetici "yapılmamış" olarak sonuçlandırdı.
 */
class PaymentRefundFailedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PaymentRefund $refund,
        public readonly User $causer,
    ) {}
}
