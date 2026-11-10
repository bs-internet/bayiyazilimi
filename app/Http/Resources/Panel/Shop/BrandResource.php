<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_url' => $this->logoUrl(),
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'products_count' => $this->products_count ?? null,
        ];
    }
}
