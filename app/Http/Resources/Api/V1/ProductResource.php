<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST API ürün gösterimi. Tutarlar kesin ondalık dizedir (birim fiyat 4
 * basamak); stok: satılabilir toplam + depo bazında miktarlar.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $stocks = $product->relationLoaded('stocks') ? $product->stocks : collect();

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'status' => $product->status->value,
            'unit' => $product->unit,
            'prices' => [
                'price' => (string) $product->price,
                'compare_price' => $product->compare_price === null ? null : (string) $product->compare_price,
                'currency' => $product->currency?->code,
                'tax_rate' => (string) $product->tax_rate,
            ],
            'stock' => [
                'track_stock' => (bool) $product->track_stock,
                'tracking' => $product->trackingMode()->value,
                'sellable' => (int) $product->stock,
                'total' => (int) $stocks->sum('quantity'),
                'warehouses' => $stocks
                    ->filter(fn (ProductStock $stock): bool => $stock->warehouse !== null)
                    ->sortBy(fn (ProductStock $stock): string => (string) $stock->warehouse?->code)
                    ->map(fn (ProductStock $stock): array => [
                        'code' => $stock->warehouse?->code,
                        'name' => $stock->warehouse?->name,
                        'quantity' => (int) $stock->quantity,
                    ])
                    ->values()
                    ->all(),
            ],
            'min_qty' => $product->min_qty,
            'max_qty' => $product->max_qty,
            'delivery_days' => $product->delivery_days,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
