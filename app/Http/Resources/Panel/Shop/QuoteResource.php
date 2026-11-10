<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Currency;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = Currency::withTrashed()->where('code', $this->currency_code)->first();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'created_at' => $this->created_at,
            'customer_name' => $this->customer_name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'grand_total' => $this->grand_total,
            'grand_total_formatted' => $currency?->format($this->grand_total) ?? $this->grand_total.' '.$this->currency_code,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'valid_until' => $this->valid_until?->toDateString(),
            'items_count' => $this->items_count ?? null,
            'order_id' => $this->order_id,
            'source' => $this->source,
        ];
    }
}
