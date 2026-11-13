<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Status;
use App\Http\Controllers\Api\V1\Concerns\ListsForSync;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DealerGroup;
use App\Models\Warehouse;
use App\Services\Panel\Shop\CatalogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * REST API tanım verileri: kategori ve marka (okuma products:read, yazma
 * catalog:write), depo, para birimi / kur, kargo firması (products:read) ve
 * bayi grupları (accounts:read). Yazma panelle aynı servisten geçer.
 */
class CatalogController extends Controller
{
    use ListsForSync;

    public function categories(Request $request): JsonResponse
    {
        $filters = $this->listFilters($request, ['status' => ['nullable', Rule::enum(Status::class)]]);

        $page = $this->applySync(Category::query()->with('parent:id,slug'), $filters)
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->paginate($this->perPage($filters))->withQueryString();

        return $this->paginated($page, fn (Category $c) => self::category($c));
    }

    public function storeCategory(Request $request, CatalogService $catalog): JsonResponse
    {
        $category = $catalog->saveCategory(null, $this->categoryData($request), null, false, $request->user());

        return response()->json(['data' => self::category($category->load('parent:id,slug'))], 201);
    }

    public function updateCategory(Request $request, Category $category, CatalogService $catalog): JsonResponse
    {
        $data = [...$category->only(['name', 'slug', 'parent_id', 'description', 'status', 'sort_order']), ...$this->categoryData($request, $category)];
        $category = $catalog->saveCategory($category, $data, null, false, $request->user());

        return response()->json(['data' => self::category($category->load('parent:id,slug'))]);
    }

    public function brands(Request $request): JsonResponse
    {
        $filters = $this->listFilters($request, ['status' => ['nullable', Rule::enum(Status::class)]]);

        $page = $this->applySync(Brand::query(), $filters)
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->paginate($this->perPage($filters))->withQueryString();

        return $this->paginated($page, fn (Brand $b) => self::brand($b));
    }

    public function storeBrand(Request $request, CatalogService $catalog): JsonResponse
    {
        $brand = $catalog->saveBrand(null, $this->brandData($request), null, false, $request->user());

        return response()->json(['data' => self::brand($brand)], 201);
    }

    public function updateBrand(Request $request, Brand $brand, CatalogService $catalog): JsonResponse
    {
        $data = [...$brand->only(['name', 'slug', 'description', 'status', 'sort_order']), ...$this->brandData($request, $brand)];
        $brand = $catalog->saveBrand($brand, $data, null, false, $request->user());

        return response()->json(['data' => self::brand($brand)]);
    }

    public function warehouses(): JsonResponse
    {
        return response()->json(['data' => Warehouse::query()->orderBy('sort_order')->orderBy('code')->get()->map(fn (Warehouse $w) => [
            'id' => $w->id,
            'code' => $w->code,
            'name' => $w->name,
            'is_default' => (bool) $w->is_default,
            'is_sellable' => (bool) $w->is_sellable,
            'status' => $w->status->value,
        ])->values()]);
    }

    public function currencies(): JsonResponse
    {
        return response()->json(['data' => Currency::query()->orderBy('sort_order')->orderBy('code')->get()->map(fn (Currency $c) => [
            'code' => $c->code,
            'name' => $c->name,
            'symbol' => $c->symbol,
            'exchange_rate' => (string) $c->exchange_rate,
            'rate_updated_at' => $c->rate_updated_at?->toIso8601String(),
            'status' => $c->status->value,
        ])->values()]);
    }

    public function carriers(): JsonResponse
    {
        return response()->json(['data' => Carrier::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Carrier $c) => [
            'code' => $c->code,
            'name' => $c->name,
            'tracking_url' => $c->tracking_url,
            'is_default' => (bool) $c->is_default,
            'status' => $c->status->value,
        ])->values()]);
    }

    public function dealerGroups(): JsonResponse
    {
        return response()->json(['data' => DealerGroup::query()->orderBy('name')->get()->map(fn (DealerGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'discount_rate' => (string) $g->discount_rate,
            'payment_terms' => $g->payment_terms,
            'credit_limit' => $g->credit_limit === null ? null : (string) $g->credit_limit,
            'payment_term_days' => $g->payment_term_days,
        ])->values()]);
    }

    /** @return array<string, mixed> */
    private function categoryData(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent' => ['sometimes', 'nullable', 'string', Rule::exists('categories', 'slug')],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ], [], ['name' => __('kategori adı'), 'parent' => __('üst kategori')]);

        if (array_key_exists('parent', $data)) {
            $data['parent_id'] = $data['parent'] === null ? null : Category::query()->where('slug', $data['parent'])->value('id');
            unset($data['parent']);
        }

        return $category === null ? [...['status' => Status::Active->value, 'sort_order' => 0], ...$data] : $data;
    }

    /** @return array<string, mixed> */
    private function brandData(Request $request, ?Brand $brand = null): array
    {
        $data = $request->validate([
            'name' => [$brand ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ], [], ['name' => __('marka adı')]);

        return $brand === null ? [...['status' => Status::Active->value, 'sort_order' => 0], ...$data] : $data;
    }

    /** @return array<string, mixed> */
    public static function category(Category $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'parent' => $c->parent?->slug,
            'description' => $c->description,
            'status' => $c->status->value,
            'sort_order' => (int) $c->sort_order,
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function brand(Brand $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'description' => $b->description,
            'status' => $b->status->value,
            'sort_order' => (int) $b->sort_order,
            'updated_at' => $b->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Sayfalayıcıyı Laravel kaynak koleksiyonlarıyla aynı biçimde döndürür (data, links, meta).
     *
     * @param  LengthAwarePaginator<int, mixed>  $page
     */
    private function paginated($page, callable $map): JsonResponse
    {
        $array = $page->toArray();

        return response()->json([
            'data' => array_map($map, $page->items()),
            'links' => ['first' => $array['first_page_url'], 'last' => $array['last_page_url'], 'prev' => $array['prev_page_url'], 'next' => $array['next_page_url']],
            'meta' => ['current_page' => $array['current_page'], 'from' => $array['from'], 'last_page' => $array['last_page'], 'path' => $array['path'], 'per_page' => $array['per_page'], 'to' => $array['to'], 'total' => $array['total']],
        ]);
    }
}
