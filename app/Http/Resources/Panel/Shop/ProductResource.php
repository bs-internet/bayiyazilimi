<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'model_number' => $this->model_number,
            'name' => $this->name,
            'slug' => $this->slug,
            'image' => $this->relationLoaded('images') ? $this->images->first()?->url() : null,
            'brand' => $this->brand?->name,
            'category' => $this->category?->name,
            'price' => $this->price,
            'price_formatted' => $currency !== null ? $currency->format($this->price) : Money::format($this->price),
            'currency_code' => $currency?->code,
            'tax_rate' => $this->tax_rate,
            'stock' => $this->stock,
            'track_stock' => $this->track_stock,
            'sale_mode' => $this->sale_mode->value,
            'sale_mode_label' => $this->sale_mode->label(),
            'status' => $this->status,
            'updated_at' => $this->updated_at,
            'badges' => self::badges($this->resource),
            'storefront_url' => self::storefrontUrl($this->resource),
        ];
    }

    /**
     * Ürün adının yanındaki ek rozetler; modüller ekler (`shop.product.badges`;
     * ör. vitrin: "Bayi" — yalnız bayilere açık ürün).
     *
     * @return array<int, array{label: string, variant?: string}>
     */
    public static function badges(Product $product): array
    {
        return hooks()->apply('shop.product.badges', [], product: $product);
    }

    /**
     * Ürünün herkese açık adresi: vitrin modülü kuruluysa ve ürün ziyaretçiye
     * açıksa (`shop.product.storefront_url`); yoksa null ("Vitrinde gör" gizlenir).
     */
    public static function storefrontUrl(Product $product): ?string
    {
        return hooks()->apply('shop.product.storefront_url', null, product: $product);
    }
}
