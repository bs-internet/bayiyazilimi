<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Models\Category;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\ConstrainsProducts;
use App\Support\Shop\Campaigns\Operators;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Ürünün kategorisi; seçilen kategorinin alt kategorileri de dahildir.
 */
final class CategoryCondition extends Condition implements ConstrainsProducts
{
    public function key(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return __('Kategori');
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
        $all = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name']);
        $byParent = $all->groupBy(fn (Category $c) => $c->parent_id ?? '');
        $result = [];

        $walk = function (string $parentId, array $trail) use (&$walk, $byParent, &$result): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                $path = [...$trail, $category->name];
                $result[] = ['value' => $category->id, 'label' => implode(' › ', $path)];
                $walk($category->id, $path);
            }
        };
        $walk('', []);

        return $result;
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::within($this->lineage($line['product']->category_id ?? null), $operator, $value);
    }

    /**
     * Kategori ve tüm üst kategorileri (alt kategoriler üstün kampanyasına girer).
     *
     * @return array<int, string>
     */
    private function lineage(?string $categoryId): array
    {
        // Önbellekli ağaç; kategori kaydedilince tazelenir.
        $parents = Category::flatTree()->pluck('parent_id', 'id');
        $ids = [];

        while ($categoryId !== null && ! in_array($categoryId, $ids, true) && count($ids) < 20) {
            $ids[] = $categoryId;
            $categoryId = $parents[$categoryId] ?? null;
        }

        return $ids;
    }

    /**
     * Seçilen kategoriler ve tüm alt kategorileri (matches()'teki soy kuralıyla aynı).
     */
    public function constrain(Builder $products, string $operator, mixed $value): void
    {
        $selected = array_map('strval', (array) $value);
        $parents = Category::flatTree()->pluck('parent_id', 'id');
        $ids = $parents->keys()
            ->filter(fn ($id) => array_intersect($this->lineage((string) $id), $selected) !== [])
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        Operators::constrainIn($products, 'products.category_id', $operator, $value, $ids);
    }
}
