<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Enums\Shop\ReturnReason;
use App\Http\Controllers\App\Shop\Concerns\ResolvesMemberArea;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\Shop\ReturnService;
use App\Services\Shop\ShopSettings;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Üye alanı: İadelerim — iade talebi oluşturma ve takip.
 */
class ReturnController extends Controller
{
    use ResolvesMemberArea;

    public function index(ReturnService $returns): Response
    {
        $requests = ReturnRequest::query()
            ->with(['order:id,number,currency_code'])
            ->withCount('items')
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->through(fn (ReturnRequest $r) => self::row($r));

        // İade talebi açılabilecek siparişler.
        $eligible = Order::query()
            ->whereIn('status', ['shipped', 'completed'])
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn (Order $o) => $returns->isEligible($o) && array_sum($returns->returnableQuantities($o)) > 0)
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'created_at' => $o->created_at?->toIso8601String(),
                'grand_total' => OrderController::money($o->grand_total, $o->currency_code),
            ])
            ->values();

        return Inertia::render($this->page('returns/index'), [
            'returns' => $requests,
            'eligibleOrders' => $eligible,
            'returnDays' => ShopSettings::int('return_days'),
        ]);
    }

    public function create(Request $request, Order $order, ReturnService $returns): Response|RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        if (! $returns->isEligible($order)) {
            return to_route($this->areaRoute('returns.index'))
                ->with('toast', ['type' => 'warning', 'message' => __('Bu sipariş için iade talebi oluşturulamaz (süre dolmuş ya da sipariş henüz kargolanmamış).')]);
        }

        $order->load('items');
        $available = $returns->returnableQuantities($order);

        return Inertia::render($this->page('returns/create'), [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                    'returnable' => $available[$i->id] ?? 0,
                    'line_total' => OrderController::money($i->line_total, $order->currency_code),
                ])->values(),
            ],
            'reasons' => ReturnReason::options(),
            'returnDays' => ShopSettings::int('return_days'),
        ]);
    }

    public function store(Request $request, Order $order, ReturnService $returns): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'reason' => __('iade nedeni'),
            'customer_note' => __('açıklama'),
            'items' => __('ürünler'),
        ]);

        $return = $returns->request($order, $request->user(), $data);

        return to_route($this->areaRoute('returns.show'), $return->id)
            ->with('toast', ['type' => 'success', 'message' => __('İade talebiniz alındı (:number).', ['number' => $return->number])]);
    }

    public function show(Request $request, ReturnRequest $returnRequest): Response
    {
        abort_unless($returnRequest->account_id !== null && $returnRequest->account_id === $request->user()?->account_id, 404);

        $returnRequest->load(['order', 'items.orderItem']);
        $code = $returnRequest->order->currency_code;

        return Inertia::render($this->page('returns/show'), [
            'returnRequest' => [
                ...self::row($returnRequest),
                'customer_note' => $returnRequest->customer_note,
                'admin_note' => $returnRequest->admin_note,
                'refund_amount' => $returnRequest->refund_amount !== null ? OrderController::money($returnRequest->refund_amount, $code) : null,
                'store_address' => ShopSettings::storeAddressLine(),
                'items' => $returnRequest->items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->orderItem?->name,
                    'sku' => $i->orderItem?->sku,
                    'quantity' => $i->quantity,
                ])->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(ReturnRequest $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->number,
            'order_id' => $r->order_id,
            'order_number' => $r->order?->number,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'reason_label' => $r->reason->label(),
            'items_count' => $r->items_count ?? $r->items->count(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->account_id !== null && $order->account_id === $request->user()?->account_id, 404);
    }
}
