<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Status;
use App\Http\Controllers\Api\V1\Concerns\ListsForSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\Panel\Shop\ProductService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * REST API ürünler: liste / tekil (products:read), güncelleme (products:write).
 * Güncelleme panelin ürün formuyla aynı servisten geçer (ProductService).
 */
class ProductsController extends Controller
{
    use ListsForSync;

    private const RELATIONS = ['currency', 'stocks.warehouse'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $this->listFilters($request, [
            'sku' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::enum(Status::class)],
        ]);

        $query = Product::query()
            ->with(self::RELATIONS)
            ->when($filters['sku'] ?? null, fn ($q, string $sku) => $q->where('sku', $sku))
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
}
