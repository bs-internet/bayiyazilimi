<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\ReturnStatus;
use App\Http\Controllers\App\Shop\OrderController as MemberOrderController;
use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Shop\ReturnService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel: İade talepleri.
 */
class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $account = (string) $request->query('account', '');

        $returns = ReturnRequest::query()
            ->with(['order:id,number,customer_name,email,currency_code'])
            ->withCount('items')
            ->when($status !== '' && ReturnStatus::tryFrom($status), fn (Builder $q) => $q->where('status', $status))
            ->when($account !== '', fn (Builder $q) => $q->where('account_id', $account))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('order', fn (Builder $o) => $o
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString()
            ->through(fn (ReturnRequest $r) => [
                'id' => $r->id,
                'number' => $r->number,
                'order_id' => $r->order_id,
                'order_number' => $r->order?->number,
                'customer_name' => $r->order?->customer_name,
                'email' => $r->order?->email,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'reason_label' => $r->reason->label(),
                'items_count' => $r->items_count,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return Inertia::render('panel/shop/returns/index', [
            'returns' => $returns,
            'filters' => ['search' => $search, 'status' => $status, 'account' => $account],
            'statuses' => ReturnStatus::options(),
        ]);
    }

    public function show(ReturnRequest $returnRequest, ReturnService $returns): Response
    {
        $returnRequest->load(['order', 'items.orderItem', 'user:id,name,email', 'resolver:id,name']);
        $order = $returnRequest->order;
        $code = $order->currency_code;

        // Önerilen (ve en fazla) iade: satır tutarının iade adedine düşen payı.
        $suggestedRefund = $returns->returnValue($returnRequest);

        return Inertia::render('panel/shop/returns/show', [
            'returnRequest' => [
                'id' => $returnRequest->id,
                'number' => $returnRequest->number,
                'status' => $returnRequest->status->value,
                'status_label' => $returnRequest->status->label(),
                'is_final' => $returnRequest->status->isFinal(),
                'reason_label' => $returnRequest->reason->label(),
                'customer_note' => $returnRequest->customer_note,
                'admin_note' => $returnRequest->admin_note,
                'refund_amount' => $returnRequest->refund_amount,
                'suggested_refund' => $suggestedRefund,
                'currency_code' => $code,
                'restocked' => $returnRequest->restocked,
                'created_at' => $returnRequest->created_at?->toIso8601String(),
                'resolved_at' => $returnRequest->resolved_at?->toIso8601String(),
                'resolver' => $returnRequest->resolver?->name,
                'customer' => $returnRequest->user?->name ?? $order->customer_name,
                'order' => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'email' => $order->email,
                    'phone' => $order->phone,
                    'status_label' => $order->status->label(),
                    'payment_status' => $order->payment_status->value,
                    'payment_status_label' => $order->payment_status->label(),
                    'grand_total' => MemberOrderController::money($order->grand_total, $code),
                ],
                'items' => $returnRequest->items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->orderItem?->name,
                    'sku' => $i->orderItem?->sku,
                    'quantity' => $i->quantity,
                    'ordered' => $i->orderItem?->quantity,
                    'unit_total' => $i->orderItem && $i->orderItem->quantity > 0
                        ? MemberOrderController::money(Money::div($i->orderItem->line_total, $i->orderItem->quantity), $code)
                        : null,
                ])->values(),
            ],
            // Yalnız izinli geçişler (mevcut durum: not güncellemek için); stoğa alınmış talep reddedilemez.
            'statuses' => array_values(array_filter(
                $returnRequest->status->targetOptions(),
                fn (array $o) => ! ($returnRequest->restocked && $o['value'] === ReturnStatus::Rejected->value),
            )),
        ]);
    }

    public function status(Request $request, ReturnRequest $returnRequest, ReturnService $returns): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ReturnStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'restock' => ['boolean'],
            'mark_refunded' => ['boolean'],
        ], [], [
            'status' => __('durum'),
            'admin_note' => __('not'),
            'refund_amount' => __('iade tutarı'),
        ]);

        $returns->changeStatus($returnRequest, ReturnStatus::from($data['status']), $request->user(), [
            'admin_note' => $data['admin_note'] ?? null,
            'refund_amount' => $data['refund_amount'] ?? null,
            'restock' => (bool) ($data['restock'] ?? false),
            'mark_refunded' => (bool) ($data['mark_refunded'] ?? false),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __(':number iade talebi güncellendi.', ['number' => $returnRequest->number])]);
    }
}
