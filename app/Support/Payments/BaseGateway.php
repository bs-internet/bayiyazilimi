<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Support\Money;
use Brick\Math\BigDecimal;

/**
 * Sürücüler için varsayılanlar: açıklama yok, tutar farkı yok, zaman aşımı yok.
 */
abstract class BaseGateway implements PaymentGateway
{
    public function description(PaymentContext $context): ?string
    {
        return null;
    }

    public function icon(): string
    {
        return 'creditCard';
    }

    public function adjustmentRate(PaymentContext $context): BigDecimal
    {
        return Money::round(0);
    }

    public function adjustmentLabel(PaymentContext $context): ?string
    {
        return null;
    }

    public function pendingTimeout(): ?int
    {
        return null;
    }
}
