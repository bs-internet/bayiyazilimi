<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\ConstrainsProducts;
use App\Support\Shop\Campaigns\Operators;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Seçilen ürünler (ürün seçiciyle).
 */
final class ProductCondition extends Condition implements ConstrainsProducts
{
    public function key(): string
    {
        return 'product';
    }

    public function label(): string
    {
        return __('Ürün');
    }

    public function scope(): string
    {
        return self::PRODUCT;
    }

    public function operators(): array
    {
        return Operators::membership();
    }

    public function input(): string
    {
        return 'products';
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::within($line['product']->id ?? null, $operator, $value);
    }

    public function constrain(Builder $products, string $operator, mixed $value): void
    {
        Operators::constrainIn($products, 'products.id', $operator, $value);
    }
}
