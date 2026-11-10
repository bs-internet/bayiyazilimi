<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\ConstrainsProducts;
use App\Support\Shop\Campaigns\Operators;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Sepet satırındaki adet (aynı üründen).
 */
final class LineQuantityCondition extends Condition implements ConstrainsProducts
{
    public function key(): string
    {
        return 'line_quantity';
    }

    public function label(): string
    {
        return __('Üründen alınan adet');
    }

    public function scope(): string
    {
        return self::PRODUCT;
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
        return $context->preview || $line === null || Operators::compare($line['quantity'], $operator, $value);
    }

    /**
     * Listede sepet yoktur; adet koşulu sağlanmış sayılır (ürün kartı etiketi gibi).
     */
    public function constrain(Builder $products, string $operator, mixed $value): void {}
}
