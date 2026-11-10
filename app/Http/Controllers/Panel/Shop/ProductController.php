<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Requests\Panel\Shop\SaveProductRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Shop\ProductResource;
use App\Models\Currency;
use App\Models\Media;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Panel\Shop\CatalogService;
use App\Services\Panel\Shop\ProductService;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use BuildsShopOptions;

    public function index(Request $request): Response
    {
        // Modüller kendi süzgeç anahtarlarını ve sorgu kısıtlarını ekler (`shop.products.filter_keys`, `shop.products.index_query`).
        $filters = $request->only(['q', 'category', 'brand', 'status', 'sale_mode', 'stock', ...hooks()->apply('shop.products.filter_keys', [])]);

        $query = Product::query()
            ->with(['brand:id,name', 'category:id,name', 'currency', 'images'])
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['brand'] ?? null, fn ($q, $v) => $q->where('brand_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['sale_mode'] ?? null, fn ($q, $v) => $q->where('sale_mode', $v))
            ->when(($filters['stock'] ?? null) === 'in', fn ($q) => $q->where('stock', '>', 0))
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->where('stock', '<=', 0));

        hooks()->do('shop.products.index_query', query: $query, filters: $filters);

        $products = $query->orderByDesc('updated_at')->paginate(Pagination::PER_PAGE)->withQueryString();

        return Inertia::render('panel/shop/products/index', [
            'products' => PaginatedResource::make($products, ProductResource::class, $request),
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
            'brands' => $this->brandOptions(),
            'saleModes' => $this->saleModeOptions(),
            // Modüllerin liste ekranı verisi (ör. ek süzgeç seçenekleri), modül anahtarıyla.
            'extensions' => hooks()->apply('shop.products.index_props', []),
        ]);
    }

    /**
     * Ürün seçici için JSON arama (ad / SKU / barkod).
     */
    public function search(Request $request): JsonResponse
    {
        $items = Product::query()
            ->search($request->string('q')->toString())
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'sku', 'name', 'status'])
            ->map(fn (Product $p) => ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'status' => $p->status])
            ->all();

        return response()->json(['data' => $items]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/shop/products/create', $this->formProps(null));
    }

    public function store(SaveProductRequest $request, ProductService $service): RedirectResponse
    {
        $product = $service->create($request->validated(), $request->user());

        return to_route('panel.shop.products.edit', $product->id)
            ->with('toast', ['type' => 'success', 'message' => __('Ürün oluşturuldu. Şimdi görsel ekleyebilirsiniz.')]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('panel/shop/products/edit', $this->formProps($product));
    }

    public function update(SaveProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->update($product, $request->validated(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Ürün güncellendi.')]);
    }

    public function status(Request $request, Product $product, CatalogService $catalog): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($product->hasStatus($request->input('status'))) {
            return back();
        }

        $catalog->toggleStatus($product, 'product', __(':sku ürün', ['sku' => $product->sku]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Ürün durumu güncellendi.')]);
    }

    public function destroy(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->delete($product, $request->user());

        return to_route('panel.shop.products.index')
            ->with('toast', ['type' => 'success', 'message' => __(':name silindi.', ['name' => $product->name])]);
    }

    public function storeImages(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['image', 'max:8192'],
        ]);

        $count = $service->addImages($product, $request->file('images', []), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':count görsel yüklendi.', ['count' => $count])]);
    }

    public function reorderImages(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['uuid'],
        ]);

        $service->reorderImages($product, $data['ids']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Görsel sırası güncellendi.')]);
    }

    public function destroyImage(Request $request, Product $product, Media $media, ProductService $service): RedirectResponse
    {
        $service->removeImage($product, $media, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Görsel silindi.')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Product $product): array
    {
        $defaultCurrencyId = Setting::get('default_currency_id');

        return [
            'product' => $product === null ? null : [
                ...$product->only([
                    'id', 'sku', 'barcode', 'model_number', 'name', 'slug', 'brand_id', 'category_id',
                    'short_description', 'description', 'notice', 'price', 'compare_price', 'currency_id',
                    'tax_rate', 'track_stock', 'allow_backorder', 'expiry_tracking', 'stock', 'min_stock', 'min_qty', 'max_qty', 'delivery_days',
                    'desi', 'unit', 'free_shipping', 'meta_title', 'meta_description',
                    'search_keywords', 'sort_order',
                ]),
                'sale_mode' => $product->sale_mode->value,
                'tracking' => $product->trackingMode()->value,
                'has_lots' => $product->lots()->exists(),
                'status' => $product->status->value,
                'badges' => ProductResource::badges($product),
                'storefront_url' => ProductResource::storefrontUrl($product),
                'highlights' => implode("\n", $product->highlights ?? []),
                'images' => $product->images()->get()->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => $m->url(),
                    'name' => $m->original_name,
                ])->all(),
                'stocks' => (function () use ($product): array {
                    // Depo başına ayrı sorgu yerine ürünün stokları tek sorguda.
                    $quantities = $product->stocks()->pluck('quantity', 'warehouse_id');

                    return Warehouse::query()->ordered()->get()->map(fn (Warehouse $w) => [
                        'warehouse_id' => $w->id,
                        'warehouse' => $w->name,
                        'is_sellable' => $w->is_sellable,
                        'quantity' => (int) ($quantities[$w->id] ?? 0),
                    ])->all();
                })(),
            ],
            'defaults' => [
                'currency_id' => $defaultCurrencyId,
                // Genel Ayarlar → varsayılan vergi oranı (yoksa %20).
                'tax_rate' => (string) Money::decimal(Tax::query()->whereKey(Setting::get('default_tax_id'))->value('rate') ?? 20)->strippedOfTrailingZeros(),
                'unit' => Unit::query()->active()->ordered()->value('name') ?? __('Adet'),
                'low_stock_threshold' => ShopSettings::int('low_stock_threshold'),
                'traceability' => ShopSettings::traceability(),
            ],
            'units' => Unit::options($product?->unit),
            'categories' => $this->categoryOptions(),
            'brands' => $this->brandOptions(),
            'saleModes' => $this->saleModeOptions(),
            // Ürün adresinin öneki (vitrin modülü kuruluysa; slug alanında gösterilir).
            'productUrlPrefix' => hooks()->apply('shop.product.url_prefix', null),
            // Modüllerin forma eklediği bölümlerin verisi, modül anahtarıyla (`shop.products.form_props`).
            'extensions' => hooks()->apply('shop.products.form_props', [], product: $product),
            'currencies' => Currency::query()
                ->where(fn ($q) => $q->where('status', Status::Active->value)
                    ->when($product?->currency_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->ordered()
                ->get(['id', 'code', 'name', 'symbol'])
                ->map(fn (Currency $c) => ['value' => $c->id, 'label' => __(':code — :name', ['code' => $c->code, 'name' => $c->name])])
                ->all(),
            'taxRates' => Tax::query()->ordered()->pluck('rate')
                ->map(fn ($r) => (string) Money::decimal($r)->strippedOfTrailingZeros())
                ->merge(['0', '10', '20'])
                ->unique()
                ->sort(fn ($a, $b) => Money::decimal($a)->compareTo(Money::decimal($b)))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function saleModeOptions(): array
    {
        return array_map(fn (SaleMode $m) => ['value' => $m->value, 'label' => $m->label()], SaleMode::cases());
    }
}
