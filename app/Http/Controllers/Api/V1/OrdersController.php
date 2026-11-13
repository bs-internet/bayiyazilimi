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
use App\Models\Account;
use App\Models\Carrier;
use App\Models\Order;
use App\Services\Api\ApiOrderService;
use App\Services\Shop\OrderService;
use Illuminate\Http\JsonResponse;
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

    /**
     * Cari hesap adına sipariş (orders:write). Birim fiyat (KDV dahil) verilmezse
     * bayinin fiyatı uygulanır; vadeli siparişte kredi kuralları panelle aynıdır.
     */
    public function store(Request $request, ApiOrderService $service): JsonResponse
    {
        $address = fn (string $key, bool $required) => [
            $key => ['sometimes', 'nullable', 'array'],
            $key.'.name' => [$required ? 'required_with:'.$key : 'nullable', 'string', 'max:255'],
            $key.'.address' => ['required_with:'.$key, 'string', 'max:1000'],
            $key.'.city' => ['nullable', 'string', 'max:100'],
            $key.'.district' => ['nullable', 'string', 'max:100'],
            $key.'.postal_code' => ['nullable', 'string', 'max:20'],
            $key.'.phone' => ['nullable', 'string', 'max:40'],
        ];

        $data = $request->validate([
            'account' => ['required', 'string', Rule::exists('accounts', 'code')],
            'payment_method' => ['required', Rule::in(ApiOrderService::PAYMENT_METHODS)],
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            'lines.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:500'],
            ...$address('shipping', true),
            ...$address('billing', true),
            'billing.tax_office' => ['nullable', 'string', 'max:100'],
            'billing.tax_number' => ['nullable', 'string', 'max:20'],
        ], [], ['account' => __('cari hesap kodu'), 'lines' => __('satırlar'), 'payment_method' => __('ödeme yöntemi')]);

        $account = Account::query()->where('code', $data['account'])->firstOrFail();
        $order = $service->place($account, $data, $request->user());

        return (new OrderResource($order->fresh(OrderResource::relations())))->response()->setStatusCode(201);
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
