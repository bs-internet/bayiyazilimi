<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Payments\PaymentMethods;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;

/**
 * Ödeme yöntemi (ör. havale / EFT ile ödemede). Yöntem ödeme adımında
 * seçilir: sepet ekranında sağlanmamış sayılır, ödeme ekranında seçilen
 * yönteme göre gösterilir; ürün kartı etiketinde sağlanmış sayılır.
 */
final class PaymentMethodCondition extends Condition
{
    public function key(): string
    {
        return 'payment_method';
    }

    public function label(): string
    {
        return __('Ödeme yöntemi');
    }

    public function scope(): string
    {
        return self::AUDIENCE;
    }

    public function operators(): array
    {
        return Operators::membership();
    }

    public function input(): string
    {
        return 'multi';
    }

    public function options(): array
    {
        return PaymentMethods::options();
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        if ($context->preview) {
            return true;
        }

        return $context->paymentMethod !== null && Operators::within($context->paymentMethod, $operator, $value);
    }
}
