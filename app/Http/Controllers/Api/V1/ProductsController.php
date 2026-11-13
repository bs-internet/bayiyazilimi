<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Http\Controllers\Api\V1\Concerns\ListsForSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Panel\Shop\ProductService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * REST API ürünler: liste / tekil (products:read); ekleme, güncelleme, silme
 * ve toplu fiyat (products:write). Yazma panelin ürün formuyla aynı servisten
 * geçer (ProductService).
 */
class ProductsController extends Controller
{
    use ListsForSync;

    private const RELATIONS = ['currency', 'stocks.warehouse'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $this->listFilters($request, [
            'sku' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(Status::class)],
        ]);

        $query = Product::query()
            ->with(self::RELATIONS)
            ->when($filters['sku'] ?? null, fn ($q, string $sku) => $q->where('sku', $sku))
            ->when($filters['q'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));

        return ProductResource::collection(
            $this->applySync($query, $filters)->paginate($this->perPage($filters))->withQueryString(),
        );
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::RELATIONS));
    }

    public function update(UpdateProductRequest $request, Product $product, ProductService $service): ProductResource
    {
        $input = $request->validated();

        foreach (['price', 'compare_price'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                $input[$key] = Money::round((string) $input[$key], 4);
            }
        }

        if (array_key_exists('tax_rate', $input)) {
            $input['tax_rate'] = Money::round((string) $input['tax_rate']);
        }

        // Servis tam form verisi bekler: ad / kısa ad / karşılaştırma fiyatı
        // gönderilmediyse mevcut değerleri korunur (boş karşılaştırma fiyatı kaldırılır).
        $data = [
            'name' => $product->name,
            'slug' => $product->slug,
            'compare_price' => $product->compare_price,
            ...$input,
        ];

        $service->update($product, $data, $request->user());

        return new ProductResource($product->fresh(self::RELATIONS));
    }

    public function store(StoreProductRequest $request, ProductService $service): JsonResponse
    {
        $input = $request->validated();

        $product = $service->create([
            'sku' => $input['sku'],
            'name' => $input['name'],
            'barcode' => $input['barcode'] ?? null,
            'model_number' => $input['model_number'] ?? null,
            'category_id' => isset($input['category']) ? Category::query()->where('slug', $input['category'])->value('id') : null,
            'brand_id' => isset($input['brand']) ? Brand::query()->where('slug', $input['brand'])->value('id') : null,
            'short_description' => $input['short_description'] ?? null,
            'description' => $input['description'] ?? null,
            'price' => Money::round((string) $input['price'], 4),
            'compare_price' => isset($input['compare_price']) ? Money::round((string) $input['compare_price'], 4) : null,
            'currency_id' => Currency::query()->where('code', $input['currency'] ?? Money::baseCode())->value('id'),
            'tax_rate' => Money::round((string) $input['tax_rate']),
            'sale_mode' => $input['sale_mode'] ?? SaleMode::Cart->value,
            'status' => $input['status'] ?? Status::Active->value,
            'unit' => $input['unit'] ?? (Unit::options()[0]['value'] ?? 'Adet'),
            'track_stock' => $input['track_stock'] ?? true,
            'tracking' => $input['tracking'] ?? StockTracking::None->value,
            'min_qty' => $input['min_qty'] ?? 1,
            'max_qty' => $input['max_qty'] ?? null,
            'min_stock' => $input['min_stock'] ?? null,
            'delivery_days' => $input['delivery_days'] ?? 0,
            'desi' => Money::round((string) ($input['desi'] ?? 0)),
        ], $request->user());

        return (new ProductResource($product->fresh(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Product $product, ProductService $service): Response
    {
        $service->delete($product, $request->user());

        return response()->noContent();
    }

    /**
     * Toplu fiyat: en çok 1.000 ürün. Her satır panelin ürün güncellemesinden
     * geçer; değişmeyen ürün yazılmaz. Bilinmeyen stok kodu tüm isteği reddeder.
     */
    public function prices(Request $request, ProductService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.compare_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [], ['items' => __('satırlar')]);

        $products = Product::query()->whereIn('sku', array_column($data['items'], 'sku'))->get()->keyBy('sku');
        $missing = array_values(array_diff(array_column($data['items'], 'sku'), $products->keys()->all()));

        if ($missing !== []) {
            throw ValidationException::withMessages(['items' => __('Bulunamayan stok kodları: :skus.', ['skus' => implode(', ', array_slice($missing, 0, 20))])]);
        }

        $result = DB::transaction(function () use ($data, $products, $service, $request): array {
            $rows = [];

            foreach ($data['items'] as $item) {
                /** @var Product $product */
                $product = $products[$item['sku']];
                $price = Money::round((string) $item['price'], 4);
                $compare = array_key_exists('compare_price', $item)
                    ? ($item['compare_price'] === null ? null : Money::round((string) $item['compare_price'], 4))
                    : $product->compare_price;
                $changed = ! $product->price->isEqualTo($price) || (string) $compare !== (string) $product->compare_price;

                if ($changed) {
                    $service->update($product, ['name' => $product->name, 'slug' => $product->slug, 'price' => $price, 'compare_price' => $compare], $request->user());
                }

                $rows[] = ['sku' => $product->sku, 'price' => (string) $price, 'compare_price' => $compare === null ? null : (string) $compare, 'changed' => $changed];
            }

            return $rows;
        });

        return response()->json([
            'data' => $result,
            'meta' => ['count' => count($result), 'changed' => count(array_filter($result, fn (array $r) => $r['changed']))],
        ]);
    }
}
