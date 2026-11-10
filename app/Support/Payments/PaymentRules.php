<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Services\Shop\ShopSettings;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use Brick\Math\BigDecimal;

/**
 * Sürücüden bağımsız yöntem kuralları: müşterinin izinli ödeme yöntemleri
 * (bayi / bayi grubu) ve yönteme göre en düşük sipariş tutarı (Cari Ayarları,
 * ana para biriminde).
 */
final class PaymentRules
{
    public static function allows(PaymentGateway $gateway, PaymentContext $context): bool
    {
        if ($context->purpose !== PaymentContext::PURPOSE_ORDER) {
            return true;
        }

        if ($context->account !== null && ! CreditTerms::for($context->account)->allowsMethod($gateway->key())) {
            return false;
        }

        $min = self::minAmount($gateway->key());

        return ! $min->isPositive() || ! $context->total->isPositive() || self::inBase($context)->isGreaterThanOrEqualTo($min);
    }

    /**
     * Yöntemin en düşük sipariş tutarı (ana para biriminde); yoksa 0.
     */
    public static function minAmount(string $key): BigDecimal
    {
        return self::minAmounts()[$key] ?? Money::round(0);
    }

    /**
     * @return array<string, BigDecimal>
     */
    public static function minAmounts(): array
    {
        $decoded = json_decode((string) ShopSettings::get('payment_min_amounts'), true);

        return is_array($decoded)
            ? array_map(fn ($v) => Money::max(0, Money::round((string) $v)), array_filter($decoded, 'is_numeric'))
            : [];
    }

    private static function inBase(PaymentContext $context): BigDecimal
    {
        return Money::toBase($context->total, $context->currency);
    }
}
