<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->imageUrl(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'products_count' => $this->products_count ?? null,
        ];
    }
}
