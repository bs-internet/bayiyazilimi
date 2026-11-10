<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST API sipariş gösterimi: başlık, müşteri / cari, adresler, satırlar,
 * toplamlar, ödeme, fatura ve kargo. Tutarlar sipariş para biriminde kesin
 * ondalık dizedir.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $money = fn (mixed $value): ?string => $value === null ? null : (string) $value;

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->email,
                'phone' => $order->phone,
            ],
            'account' => $order->account === null ? null : [
                'code' => $order->account->code,
                'title' => $order->account->title,
                'type' => $order->account->type?->value,
            ],
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'currency' => $order->currency_code,
            'exchange_rate' => $money($order->exchange_rate),
            'lines' => $order->items->map(fn (OrderItem $item): array => [
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount_amount' => $money($item->discount_amount),
                'tax_rate' => (string) $item->tax_rate,
                'tax_amount' => $money($item->tax_amount),
                'line_total' => (string) $item->line_total,
                'campaign' => $item->campaign,
            ])->values()->all(),
            'totals' => [
                'subtotal' => $money($order->subtotal),
                'discount_total' => $money($order->discount_total),
                'shipping_total' => $money($order->shipping_total),
                'payment_adjustment' => $money($order->payment_adjustment),
                'tax_total' => $money($order->tax_total),
                'grand_total' => $money($order->grand_total),
            ],
            'payment' => [
                'method' => $order->payment_method,
                'method_label' => $order->paymentMethodLabel(),
                'status' => $order->payment_status->value,
                'status_label' => $order->payment_status->label(),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'due_date' => $order->due_date?->toDateString(),
            ],
            'invoice' => [
                'number' => $order->invoice_number,
                'invoiced_at' => $order->invoiced_at?->toIso8601String(),
            ],
            'shipping' => [
                'warehouse' => $order->warehouse?->code,
                'carrier' => $order->carrier?->code,
                'cargo_company' => $order->cargo_company,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->carrier?->trackingLink($order->tracking_number),
                'shipped_at' => $order->shipped_at?->toIso8601String(),
            ],
            'customer_note' => $order->customer_note,
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Listede ve tekil gösterimde yüklenen ilişkiler (N+1 yok).
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['items', 'account', 'carrier', 'warehouse'];
    }
}
