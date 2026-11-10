<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Shop\OrderResource;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\LocationStock;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\StockLot;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\OrderService;
use App\Services\Shop\PaymentRefundService;
use App\Services\Shop\PickListBuilder;
use App\Services\Shop\StockService;
use App\Support\Money;
use App\Support\Pagination;
use App\Support\Payments\PaymentMethods;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\RefundsPayments;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'payment_status', 'payment_method', 'from', 'to', 'account', 'invoice']);

        $orders = Order::query()
            ->with('account:id,type')
            ->withCount('items')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', "%{$v}%")
                ->orWhere('customer_name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")
                ->orWhere('phone', 'like', "%{$v}%")))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($filters['payment_method'] ?? null, fn ($q, $v) => $q->where('payment_method', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['account'] ?? null, fn ($q, $v) => $q->where('account_id', $v))
            // Fatura: kesildi = fatura no girilmiş; kesilmedi = tamamlanmış ama faturası kesilmemiş.
            ->when(($filters['invoice'] ?? null) === 'kesildi', fn ($q) => $q->whereNotNull('invoice_number'))
            ->when(($filters['invoice'] ?? null) === 'kesilmedi', fn ($q) => $q->where('status', OrderStatus::Completed->value)->whereNull('invoice_number'))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        return Inertia::render('panel/shop/orders/index', [
            'orders' => PaginatedResource::make($orders, OrderResource::class, $request),
            'filters' => $filters,
            'statuses' => OrderStatus::options(),
            'paymentStatuses' => PaymentStatus::allOptions(),
            'paymentMethods' => PaymentMethods::options(Order::query()->distinct()->pluck('payment_method')->all()),
        ]);
    }

    /**
     * Çoklu sipariş toplama listesi (yazdırılabilir). Sipariş seçilmezse
     * "Hazırlanıyor" durumundaki siparişler (en eskiden) alınır.
     */
    public function pickList(Request $request, PickListBuilder $builder): Response
    {
        $ids = array_values(array_filter((array) $request->input('orders', []), 'is_string'));

        $orders = Order::query()
            ->with('items')
            ->when($ids !== [], fn (Builder $q) => $q->whereKey($ids), fn (Builder $q) => $q->where('status', OrderStatus::Processing->value))
            ->oldest()
            ->limit(PickListBuilder::MAX_ORDERS + 1)
            ->get();

        $truncated = $orders->count() > PickListBuilder::MAX_ORDERS;
        $orders = $orders->take(PickListBuilder::MAX_ORDERS);

        return Inertia::render('panel/shop/orders/pick-list', [
            'selected' => $ids !== [],
            'truncated' => $truncated,
            'maxOrders' => PickListBuilder::MAX_ORDERS,
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer' => $o->customer_name,
                'company' => is_array($o->billing_address) ? ($o->billing_address['company'] ?? null) : null,
                'city' => is_array($o->shipping_address) ? ($o->shipping_address['city'] ?? null) : null,
                'status_label' => $o->status->label(),
                'items' => (int) $o->items->sum('quantity'),
                'created_at' => $o->created_at,
            ])->values(),
            'list' => $builder->build($orders),
            'generatedAt' => now(),
        ]);
    }

    public function show(Request $request, Order $order, StockService $stock): Response
    {
        $order->load(['items.product:id,slug,tracking', 'items.product.images', 'histories.user:id,name', 'payments.refunds.user:id,name', 'quote:id,number', 'user:id,name,email', 'carrier', 'account:id,type,title']);
        $currency = Currency::withTrashed()->where('code', $order->currency_code)->first();
        $fmt = fn ($v) => Money::formatIn($v, $order->currency_code);
        $accountOrders = $order->account_id ? Order::query()->where('account_id', $order->account_id)->count() : null;

        // Toplama: stoğun düştüğü depo / lokasyon / lotlar (ürün başına, net).
        $picks = $order->stock_deducted ? $stock->orderPicks($order)->groupBy('product_id') : collect();

        // Lot değiştirme seçenekleri: siparişteki lot takipli ürünlerin stokta olan, süresi geçmemiş lotları.
        $lotOptions = $order->stock_deducted ? $this->lotOptions($order) : collect();

        return Inertia::render('panel/shop/orders/show', [
            'order' => [
                ...OrderResource::make($order)->resolve($request),
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'customer_note' => $order->customer_note,
                'admin_note' => $order->admin_note,
                'cargo_company' => $order->cargo_company,
                'carrier_id' => $order->carrier_id,
                'carrier' => $order->carrier?->name,
                'total_desi' => Money::decimal($order->total_desi),
                'tracking_number' => $order->tracking_number,
                'tracking_link' => $order->carrier?->trackingLink($order->tracking_number),
                'exchange_rate' => $order->exchange_rate,
                'paid_at' => $order->paid_at,
                'due_date' => $order->due_date?->toDateString(),
                'payment_adjustment_formatted' => ! Money::decimal($order->payment_adjustment)->isZero() ? $fmt($order->payment_adjustment) : null,
                'payment_adjustment_label' => $order->payment_adjustment_label,
                'shipped_at' => $order->shipped_at,
                'cancelled_at' => $order->cancelled_at,
                'invoiced_at' => $order->invoiced_at,
                'stock_deducted' => $order->stock_deducted,
                'ip_address' => $order->ip_address,
                'subtotal_formatted' => $fmt($order->subtotal),
                'tax_total_formatted' => $fmt($order->tax_total),
                'shipping_total_formatted' => Money::decimal($order->shipping_total)->isPositive() ? $fmt($order->shipping_total) : __('Ücretsiz'),
                'discount_total_formatted' => Money::decimal($order->discount_total)->isPositive() ? $fmt($order->discount_total) : null,
                // Ara toplam kampanya indirimi düşülmüş tutardır; indirim öncesi tutar özet için.
                'gross_subtotal_formatted' => Money::decimal($order->discount_total)->isPositive() ? $fmt(Money::add($order->subtotal, $order->discount_total)) : null,
                'quote' => $order->quote ? ['id' => $order->quote->id, 'number' => $order->quote->number] : null,
                'user_id' => $order->user_id,
                'user' => $order->user ? ['name' => $order->user->name, 'email' => $order->user->email] : null,
                'account' => $order->account ? [
                    'id' => $order->account->id,
                    'title' => $order->account->title,
                    'type' => $order->account->type?->value,
                    'type_label' => $order->account->type?->label(),
                    'orders_count' => $accountOrders,
                ] : null,
                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'image' => $i->product?->images->first()?->url(),
                    'sku' => $i->sku,
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'tax_rate' => $i->tax_rate,
                    'campaign' => $i->campaign,
                    'discount_formatted' => Money::decimal($i->discount_amount)->isPositive() ? $fmt($i->discount_amount) : null,
                    'unit_price_formatted' => $fmt($i->unit_price),
                    'tax_amount_formatted' => $fmt($i->tax_amount),
                    'line_total_formatted' => $fmt($i->line_total),
                    'tracking' => $i->product?->trackingMode()->value ?? 'none',
                    'picks' => $picks->get($i->product_id, collect())->map(fn (array $p) => [
                        'warehouse_id' => $p['warehouse']?->id,
                        'warehouse' => $p['warehouse']?->name,
                        'location_id' => $p['location']?->id,
                        'location' => $p['location']?->label(),
                        'lot_id' => $p['lot']?->id,
                        'lot' => $p['lot']?->code,
                        'expires_at' => $p['lot']?->expires_at?->toDateString(),
                        'quantity' => $p['quantity'],
                    ])->values()->all(),
                    'lot_options' => $lotOptions->get($i->product_id, []),
                ])->all(),
                'histories' => $order->histories->map(fn (OrderHistory $h) => [
                    'id' => $h->id,
                    'created_at' => $h->created_at,
                    'status_label' => $h->status?->label(),
                    'note' => $h->note,
                    'notify_customer' => $h->notify_customer,
                    'user' => $h->user?->name,
                ])->all(),
                'payments' => $order->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'created_at' => $p->created_at,
                    'provider' => $p->provider,
                    'method_label' => $p->methodLabel(),
                    'installment' => $p->installment,
                    'fee_formatted' => Money::decimal($p->fee_amount)->isPositive() ? $fmt($p->fee_amount) : null,
                    'merchant_oid' => $p->merchant_oid,
                    'amount' => Money::decimal($p->amount),
                    'refundable' => $p->status === PaymentStatus::Paid && app(PaymentRegistry::class)->get($p->method) instanceof RefundsPayments,
                    'refunded_formatted' => ($r = $p->refundedAmount())->isPositive() ? $fmt($r) : null,
                    // Sağlayıcı iadeleri; bir süredir "İşlemde" kalan elle sonuçlandırılır.
                    'refunds' => $p->refunds->sortByDesc('created_at')->values()->map(fn (PaymentRefund $refund) => [
                        'id' => $refund->id,
                        'created_at' => $refund->created_at,
                        'amount_formatted' => $fmt($refund->amount),
                        'status' => $refund->status->value,
                        'status_label' => $refund->status->label(),
                        'failed_reason' => $refund->failed_reason,
                        'user' => $refund->user?->name,
                        'resolvable' => $refund->status === RefundStatus::Pending
                            && $refund->created_at?->lte(now()->subMinutes(PaymentRefundService::STALE_AFTER_MINUTES)) === true,
                    ])->all(),
                    'amount_formatted' => $fmt($p->amount),
                    'status' => $p->status->value,
                    'status_label' => $p->status->label(),
                    'failed_reason' => $p->failed_reason,
                    'paid_at' => $p->paid_at,
                ])->all(),
            ],
            // Yalnız bu durumdan elle geçilebilecek durumlar (OrderStatus::manualTargets).
            'statuses' => OrderStatus::optionsFor($order->status->manualTargets()),
            'paymentStatuses' => PaymentStatus::options(),
            'carriers' => Carrier::query()
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('id', $order->carrier_id))
                ->ordered()
                ->get(['id', 'name'])
                ->map(fn (Carrier $c) => ['value' => $c->id, 'label' => $c->name])
                ->all(),
            // Eklentilerin sipariş ekranı verisi (ör. muhasebe aktarım durumu), eklenti
            // anahtarına göre; `orders.show.sidebar` yuvasındaki bileşenleri okur.
            'extensions' => (object) hooks()->apply('panel.orders.show.extensions', [], order: $order),
        ]);
    }

    public function status(Request $request, Order $order, OrderService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (OrderStatus $s): string => $s->value, $order->status->manualTargets()))],
            'note' => ['nullable', 'string', 'max:255'],
            'notify' => ['boolean'],
            'carrier_id' => ['nullable', 'uuid', 'exists:carriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        $carrier = ! empty($data['carrier_id']) ? Carrier::query()->find($data['carrier_id']) : null;

        $service->changeStatus($order, OrderStatus::from($data['status']), [
            'note' => $data['note'] ?? null,
            'notify' => (bool) ($data['notify'] ?? false),
            'carrier_id' => $carrier?->id,
            'cargo_company' => $carrier?->name,
            'tracking_number' => $data['tracking_number'] ?? null,
        ], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':number siparişinin durumu güncellendi.', ['number' => $order->number])]);
    }

    public function payment(Request $request, Order $order, OrderService $service): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_column(PaymentStatus::options(), 'value'))],
        ]);

        $service->updatePaymentStatus($order, PaymentStatus::from($data['payment_status']), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':number ödeme durumu güncellendi.', ['number' => $order->number])]);
    }

    /**
     * Fatura bilgisi: tamamlanan siparişe fatura no girilir (boş gönderilirse kaldırılır).
     */
    public function invoice(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:64'],
        ], [], ['invoice_number' => __('fatura no')]);

        if ($order->status !== OrderStatus::Completed) {
            throw ValidationException::withMessages(['invoice_number' => __('Fatura bilgisi yalnızca tamamlanan siparişlere girilebilir.')]);
        }

        $number = trim((string) ($data['invoice_number'] ?? ''));

        app(OrderService::class)->recordInvoice($order, $number === '' ? null : $number, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __($number === '' ? __(':number fatura bilgisi kaldırıldı.', ['number' => $order->number]) : __(':number faturalandırıldı.', ['number' => $order->number]))]);
    }

    public function note(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $order->forceFill(['admin_note' => $data['admin_note'] ?? null])->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('Sipariş notu kaydedildi.')]);
    }

    /**
     * Toplama parçasının lotunu / seri numarasını değiştirir (depo personeli
     * fiziksel olarak başka lotu topladığında).
     */
    public function changeLot(Request $request, Order $order, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'uuid', 'exists:warehouse_locations,id'],
            'from_lot_id' => ['nullable', 'uuid', Rule::exists('stock_lots', 'id')->where('product_id', $request->input('product_id'))],
            'to_lot_id' => ['required', 'uuid', Rule::exists('stock_lots', 'id')->where('product_id', $request->input('product_id'))],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [], ['to_lot_id' => __('yeni lot'), 'quantity' => __('miktar')]);

        $product = $order->items()->where('product_id', $data['product_id'])->firstOrFail()->product
            ?? throw ValidationException::withMessages(['product_id' => __('Ürün bulunamadı.')]);

        $stock->changeOrderLot(
            $order,
            $product,
            Warehouse::query()->findOrFail($data['warehouse_id']),
            isset($data['location_id']) ? WarehouseLocation::query()->findOrFail($data['location_id']) : null,
            isset($data['from_lot_id']) ? StockLot::query()->findOrFail($data['from_lot_id']) : null,
            StockLot::query()->findOrFail($data['to_lot_id']),
            (int) $data['quantity'],
            $request->user(),
        );

        $order->histories()->create([
            'status' => $order->status,
            'note' => __(':sku için lot / seri no değiştirildi.', ['sku' => $product->sku]),
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __(':sku lotu değiştirildi.', ['sku' => $product->sku])]);
    }

    /**
     * Ürün başına seçilebilir lotlar: stokta olan, süresi geçmemiş (depo bazında).
     *
     * @return Collection<array-key, array<int, array{warehouse_id: string, lot_id: string|null, label: string, quantity: int}>> ürün id → lotlar
     */
    private function lotOptions(Order $order)
    {
        $productIds = $order->items->filter(fn ($i) => $i->product?->tracksLots())->pluck('product_id')->all();

        if ($productIds === []) {
            return collect();
        }

        $today = CarbonImmutable::today();

        return LocationStock::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('stock_lot_id')
            ->where('quantity', '>', 0)
            ->with('lot:id,code,expires_at')
            ->get()
            ->reject(fn (LocationStock $s) => $s->lot === null || $s->lot->isExpired($today))
            ->groupBy(fn (LocationStock $s) => $s->product_id)
            ->map(fn ($rows) => $rows->groupBy(fn (LocationStock $s) => "{$s->warehouse_id}|{$s->stock_lot_id}")
                ->map(fn ($group) => [
                    'warehouse_id' => $group->first()->warehouse_id,
                    'lot_id' => $group->first()->stock_lot_id,
                    'label' => $group->first()->lot->label(),
                    'quantity' => (int) $group->sum('quantity'),
                ])
                ->sortBy('label')
                ->values()
                ->all());
    }
}
