<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop\Concerns;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Warehouse;

/**
 * Panel mağaza ekranlarında tekrar eden select seçenekleri.
 */
trait BuildsShopOptions
{
    /**
     * Kategoriler tam yol etiketiyle, ağaç sırasında.
     *
     * @return array<int, array{value: string, label: string, depth: int}>
     */
    protected function categoryOptions(?string $excludeId = null): array
    {
        $all = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name']);
        $byParent = $all->groupBy(fn (Category $c) => $c->parent_id ?? '');
        $result = [];

        $walk = function (string $parentId, array $trail, int $depth) use (&$walk, $byParent, &$result, $excludeId): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                if ($category->id === $excludeId) {
                    continue;
                }

                $path = [...$trail, $category->name];
                $result[] = ['value' => $category->id, 'label' => implode(' › ', $path), 'depth' => $depth];
                $walk($category->id, $path, $depth + 1);
            }
        };

        $walk('', [], 0);

        return $result;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function brandOptions(): array
    {
        return Brand::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Brand $b) => ['value' => $b->id, 'label' => $b->name])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function warehouseOptions(bool $onlyActive = false): array
    {
        return Warehouse::query()
            ->when($onlyActive, fn ($q) => $q->active())
            ->ordered()
            ->get(['id', 'name', 'code'])
            ->map(fn (Warehouse $w) => ['value' => $w->id, 'label' => __(':name (:code)', ['name' => $w->name, 'code' => $w->code])])
            ->all();
    }
}
