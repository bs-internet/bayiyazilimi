<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Shop;

use App\Models\Currency;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->billing_address['company'] ?? null,
            'grand_total' => $this->grand_total,
            'grand_total_formatted' => $currency?->format($this->grand_total) ?? $this->grand_total.' '.$this->currency_code,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->paymentMethodLabel(),
            'items_count' => $this->items_count ?? null,
            'quote_id' => $this->quote_id,
            'account_id' => $this->account_id,
            // Fatura takibi (tamamlanan siparişler).
            'invoice_number' => $this->invoice_number,
            'invoiced_at' => $this->invoiced_at,
            // Siparişi veren hesabın türü (dealer / customer); `account` yüklüyse.
            'account_type' => $this->whenLoaded('account', fn () => $this->account?->type?->value),
        ];
    }
}
