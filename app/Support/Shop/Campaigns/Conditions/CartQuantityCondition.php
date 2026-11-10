<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;

/**
 * Sepetteki toplam ürün adedi.
 */
final class CartQuantityCondition extends Condition
{
    public function key(): string
    {
        return 'cart_quantity';
    }

    public function label(): string
    {
        return __('Sepetteki ürün adedi');
    }

    public function scope(): string
    {
        return self::AUDIENCE;
    }

    public function operators(): array
    {
        return Operators::numeric();
    }

    public function input(): string
    {
        return 'number';
    }

    public function unit(): ?string
    {
        return 'adet';
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return $context->preview || Operators::compare($context->cartQuantity(), $operator, $value);
    }
}
