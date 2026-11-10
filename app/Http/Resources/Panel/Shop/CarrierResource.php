<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Carrier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Carrier
 */
class CarrierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'tracking_url' => $this->tracking_url,
            'extra_desi_price' => $this->extra_desi_price,
            'prices_include_tax' => $this->prices_include_tax,
            'delivery_days' => $this->delivery_days,
            'is_default' => $this->is_default,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'rates_count' => $this->whenCounted('rates'),
            'rates' => $this->whenLoaded('rates', fn () => $this->rates->map(fn ($r) => [
                'min_desi' => $r->min_desi,
                'max_desi' => $r->max_desi,
                'price' => $r->price,
            ])->values()->all()),
        ];
    }
}
