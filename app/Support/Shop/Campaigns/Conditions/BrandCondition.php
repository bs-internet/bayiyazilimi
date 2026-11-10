<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Models\Brand;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\ConstrainsProducts;
use App\Support\Shop\Campaigns\Operators;
use Illuminate\Contracts\Database\Query\Builder;

final class BrandCondition extends Condition implements ConstrainsProducts
{
    public function key(): string
    {
        return 'brand';
    }

    public function label(): string
    {
        return __('Marka');
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
        return 'multi';
    }

    public function options(): array
    {
        return Brand::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Brand $b) => ['value' => $b->id, 'label' => $b->name])
            ->all();
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::within($line['product']->brand_id ?? null, $operator, $value);
    }

    public function constrain(Builder $products, string $operator, mixed $value): void
    {
        Operators::constrainIn($products, 'products.brand_id', $operator, $value);
    }
}
