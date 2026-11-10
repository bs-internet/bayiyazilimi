<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Http\Controllers\Api\V1\Concerns\ListsForSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordInvoiceRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Carrier;
use App\Models\Order;
use App\Services\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * REST API siparişler: liste / tekil (orders:read); durum değişikliği ve
 * fatura bilgisi (orders:write) OrderService üzerinden — durum makinesi,
 * stok ve cari kuralları panelle aynıdır.
 */
class OrdersController extends Controller
{
    use ListsForSync;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $this->listFilters($request, [
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ]);

        $query = Order::query()
            ->with(OrderResource::relations())
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($q, string $status) => $q->where('payment_status', $status));

        return OrderResource::collection(
            $this->applySync($query, $filters)->paginate($this->perPage($filters))->withQueryString(),
        );
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(OrderResource::relations()));
    }

    public function status(UpdateOrderStatusRequest $request, Order $order, OrderService $service): OrderResource
    {
        $data = [
            'note' => $request->validated('note'),
            'notify' => $request->boolean('notify'),
        ];

        // Gönderilmeyen kargo bilgisi korunur (servis yalnız verilen anahtarları yazar).
        if ($request->exists('carrier')) {
            $carrier = filled($request->validated('carrier'))
                ? Carrier::query()->where('code', $request->validated('carrier'))->first()
                : null;

            $data['carrier_id'] = $carrier?->id;
            $data['cargo_company'] = $carrier?->name;
        }

        if ($request->exists('tracking_number')) {
            $data['tracking_number'] = $request->validated('tracking_number');
        }

        $service->changeStatus($order, OrderStatus::from($request->validated('status')), $data, $request->user());

        return new OrderResource($order->fresh(OrderResource::relations()));
    }

    public function invoice(RecordInvoiceRequest $request, Order $order, OrderService $service): OrderResource
    {
        $number = $request->validated('invoice_number');

        $service->recordInvoice($order, $number === null ? null : (string) $number, $request->user());

        return new OrderResource($order->fresh(OrderResource::relations()));
    }
}
