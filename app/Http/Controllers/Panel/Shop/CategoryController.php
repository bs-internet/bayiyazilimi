<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Requests\Panel\Shop\SaveCategoryRequest;
use App\Http\Resources\Panel\Shop\CategoryResource;
use App\Models\Category;
use App\Services\Panel\Shop\CatalogService;
use App\Support\Shop\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use BuildsShopOptions;

    public function index(Request $request): Response
    {
        $all = Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Category $c) => $c->parent_id ?? '');
        $rows = [];

        // Ağaç sırasında düzleştir (derinlik bilgisiyle).
        $walk = function (string $parentId, int $depth) use (&$walk, $byParent, &$rows, $request): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                $rows[] = [...CategoryResource::make($category)->resolve($request), 'depth' => $depth];
                $walk($category->id, $depth + 1);
            }
        };
        $walk('', 0);

        return Inertia::render('panel/shop/categories/index', [
            'categories' => $rows,
            'parents' => $this->categoryOptions(),
            // Arama motoru (meta) alanları yalnız vitrin kuruluyken.
            'storefront' => Storefront::enabled(),
        ]);
    }

    public function store(SaveCategoryRequest $request, CatalogService $catalog): RedirectResponse
    {
        $category = $catalog->saveCategory(null, $request->payload(), $request->file('image'), false, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name kategorisi oluşturuldu.', ['name' => $category->name])]);
    }

    public function update(SaveCategoryRequest $request, Category $category, CatalogService $catalog): RedirectResponse
    {
        $catalog->saveCategory($category, $request->payload(), $request->file('image'), $request->boolean('remove_image'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name kategorisi güncellendi.', ['name' => $category->name])]);
    }

    public function status(Request $request, Category $category, CatalogService $catalog): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($category->hasStatus($request->input('status'))) {
            return back();
        }

        $catalog->toggleStatus($category, 'category', __(':name kategori', ['name' => $category->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Kategori durumu güncellendi.')]);
    }

    public function destroy(Request $request, Category $category, CatalogService $catalog): RedirectResponse
    {
        $catalog->deleteCategory($category, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name kategorisi silindi.', ['name' => $category->name])]);
    }
}
