<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\PaymentNotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentNotification;
use App\Services\Shop\PaymentNotificationService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel › Ödemeler › Havale Bildirimleri: bayilerin dekontlu ödeme bildirimleri;
 * onay (ödeme kaydı + cari alacak) ya da gerekçeli ret.
 */
class PaymentNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', PaymentNotificationStatus::Pending->value);

        $notifications = PaymentNotification::query()
            ->with(['account:id,title,code', 'account.users' => fn ($q) => $q->select('id', 'account_id', 'name'), 'user:id,name', 'order:id,number', 'bankAccount:id,bank_name,iban', 'reviewer:id,name', 'media'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        return Inertia::render('panel/shop/ledger/payment-notifications', [
            'notifications' => $notifications->through(fn (PaymentNotification $n) => [
                'id' => $n->id,
                'created_at' => $n->created_at?->toIso8601String(),
                'account' => $n->account ? ['id' => $n->account->id, 'title' => $n->account->title ?? $n->account->code, 'member_id' => $n->account->users->first()?->id] : null,
                'user' => $n->user?->name,
                'order' => $n->order ? ['id' => $n->order->id, 'number' => $n->order->number] : null,
                'bank' => $n->bankAccount?->bank_name,
                'amount' => Money::decimal($n->amount),
                'amount_formatted' => Money::format($n->amount),
                'transfer_date' => $n->transfer_date->toDateString(),
                'sender_name' => $n->sender_name,
                'description' => $n->description,
                'status' => $n->status->value,
                'status_label' => $n->status->label(),
                'reject_reason' => $n->reject_reason,
                'reviewer' => $n->reviewer?->name,
                'reviewed_at' => $n->reviewed_at?->toIso8601String(),
                'has_receipt' => $n->media->contains('collection', PaymentNotification::RECEIPT),
            ]),
            'status' => $status,
            'statuses' => PaymentNotificationStatus::options(),
            'currency' => Money::baseCode(),
        ]);
    }

    /**
     * Dekont: süreli imzalı bağlantıya yönlendirir (özel disk).
     */
    public function receipt(PaymentNotification $paymentNotification): RedirectResponse
    {
        $media = $paymentNotification->firstMediaIn(PaymentNotification::RECEIPT);

        abort_if($media === null, 404);

        return redirect()->away((string) $media->temporaryUrl());
    }

    public function approve(Request $request, PaymentNotification $paymentNotification, PaymentNotificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
        ], [], ['amount' => __('tutar')]);

        $payment = $service->approve($paymentNotification, $request->user(), isset($data['amount']) ? (string) $data['amount'] : null);

        return back()->with('toast', ['type' => 'success', 'message' => __(':amount ödeme cariye işlendi.', ['amount' => Money::format($payment->amount)])]);
    }

    public function reject(Request $request, PaymentNotification $paymentNotification, PaymentNotificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => __('ret gerekçesi')]);

        $service->reject($paymentNotification, $request->user(), $data['reason']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Havale bildirimi reddedildi.')]);
    }
}
