<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Payment;
use Brick\Math\BigDecimal;

/**
 * Sağlayıcı üzerinden para iadesi. Başarısızsa ShopException fırlatır.
 */
interface RefundsPayments
{
    public function refund(Payment $payment, BigDecimal $amount): void;
}
