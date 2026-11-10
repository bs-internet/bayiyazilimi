<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Order;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        [$kind, $reference] = match ($this->reference_type) {
            (new Order)->getMorphClass() => ['order', Order::query()->whereKey($this->reference_id)->value('number')],
            (new StockReceipt)->getMorphClass() => ['receipt', StockReceipt::query()->whereKey($this->reference_id)->value('number')],
            (new StockCount)->getMorphClass() => ['count', StockCount::query()->whereKey($this->reference_id)->value('number')],
            default => [null, null],
        };

        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'product_id' => $this->product_id,
            'product_sku' => $this->product?->sku,
            'product_name' => $this->product?->name,
            'warehouse' => $this->warehouse?->name,
            'location' => $this->location?->label(),
            'lot' => $this->lot?->code,
            'lot_id' => $this->stock_lot_id,
            'expires_at' => $this->lot?->expires_at?->toDateString(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balance_after,
            'reference_kind' => $reference !== null ? $kind : null,
            'reference_id' => $reference !== null ? $this->reference_id : null,
            'reference' => $reference,
            'note' => $this->note,
            'user' => $this->user?->name,
        ];
    }
}
