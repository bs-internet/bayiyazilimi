<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * Sonucu sağlayıcıdan sorgulanabilen sürücü: zaman aşımı komutu bekleyen
 * ödemeyi "başarısız" saymadan önce `reconcile()` çağırır; ödeme sağlayıcıda
 * tamamlanmışsa (dönüş / web kancası kaybolmuşsa) burada sonuçlandırılır.
 */
interface ReconcilesPayments
{
    public function reconcile(Payment $payment): void;
}
