<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Siparişe uygulanamayan ödeme alındı: sipariş zaten ödenmiş, iptal edilmiş
 * ya da ödeme zaman aşımıyla kapanmışken sağlayıcıdan başarılı ödeme geldi.
 * Ödeme cariye alacak olarak işlenir (hesabı varsa); yönetici siparişi yeniden
 * açar ya da ödemeyi iade eder.
 */
class UnappliedPaymentReceivedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}
}
