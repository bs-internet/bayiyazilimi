<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
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
            'address' => $this->address,
            'phone' => $this->phone,
            'is_default' => $this->is_default,
            'is_sellable' => $this->is_sellable,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'total_quantity' => isset($this->stocks_sum_quantity) ? (int) $this->stocks_sum_quantity : null,
        ];
    }
}
