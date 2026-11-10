<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\App\Shop\Concerns\ResolvesMemberArea;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Services\Shop\LedgerService;
use App\Services\Shop\PaymentNotificationService;
use App\Services\Shop\ShopSettings;
use App\Support\Ledger\LedgerPresenter;
use App\Support\Money;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\PaymentRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Üye alanı: Cari Hesabım (eski Ödemelerim) — bakiye ve ekstre,
 * ödemeler (kart, havale, tahsilat), havale bekleyen siparişler, banka
 * hesapları ve havale bildirimi (dekontla).
 */
class PaymentController extends Controller
{
    use ResolvesMemberArea;

    public function index(Request $request, LedgerPresenter $ledger): Response
    {
        // Order, Payment ve PaymentNotification hesap kapsamlıdır (bind_account).
        $orders = Order::query()->with(['payments'])->latest()->limit(200)->get();
        $balance = Payment::query()->whereNull('order_id')->latest()->limit(200)->get();
        $account = $request->user()?->account;
        $banks = BankAccount::query()->active()->ordered()->get();
        // Havale bildirimi yalnız bayi panelinde (vitrin Hesabım'da değil).
        $canNotify = $account !== null && $banks->isNotEmpty() && str_starts_with($this->page('payments/index'), 'app/');

        return Inertia::render($this->page('payments/index'), [
            'ledger' => $account !== null ? $ledger->forAccount($account, $request->query('from'), $request->query('to')) : null,
            'payments' => (new PaymentRows)->build($orders, $balance),
            'bankAccounts' => ShopSettings::bool('bank_transfer_enabled') ? BankAccount::forDisplay() : null,
            // Cari tahsilat: bakiyeye kartla ödeme (kart modülü kuruluysa, yalnız bayi panelinde).
            'balancePay' => $account !== null && str_starts_with($this->page('payments/index'), 'app/') ? (function () use ($request, $account) {
                $gateways = app(PaymentRegistry::class)->balanceGateways(PaymentContext::for($request->user(), 0, Money::base(), PaymentContext::PURPOSE_BALANCE));

                return $gateways === [] ? null : [
                    'currency' => Money::baseCode(),
                    'suggested' => Money::max(0, app(LedgerService::class)->balance($account->id)),
                    'gateways' => array_values(array_map(fn ($g) => ['value' => $g->key(), 'label' => $g->label()], $gateways)),
                    'orders' => app(LedgerService::class)->openOrders($account->id),
                ];
            })() : null,
            'notify' => $canNotify ? [
                'currency' => Money::baseCode(),
                'banks' => $banks->map(fn (BankAccount $b) => ['value' => $b->id, 'label' => $b->bank_name.' · '.$b->formattedIban()])->all(),
                'orders' => $orders
                    ->filter(fn (Order $o) => in_array($o->payment_status, [PaymentStatus::Pending, PaymentStatus::Partial], true)
                        && ! in_array($o->status, [OrderStatus::Cancelled, OrderStatus::Failed], true))
                    ->map(fn (Order $o) => ['value' => $o->id, 'label' => $o->number.' · '.Money::format(app(LedgerService::class)->orderAmount($o, $o->grand_total))])
                    ->values()->all(),
                'history' => PaymentNotification::query()->with('order:id,number')->latest()->limit(20)->get()->map(fn (PaymentNotification $n) => [
                    'id' => $n->id,
                    'created_at' => $n->created_at?->toIso8601String(),
                    'transfer_date' => $n->transfer_date->toDateString(),
                    'amount' => Money::format($n->amount),
                    'order_number' => $n->order?->number,
                    'status' => $n->status->value,
                    'status_label' => $n->status->label(),
                    'reject_reason' => $n->reject_reason,
                ])->all(),
            ] : null,
        ]);
    }

    /**
     * Cari tahsilat: bakiyeye kartla ödeme; kart sürücüsünün güvenli ödeme sayfasına yönlendirir.
     */
    public function payBalance(Request $request, PaymentRegistry $payments): RedirectResponse
    {
        $account = $request->user()?->account;
        abort_if($account === null, 403);

        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:24'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'order_ids' => ['nullable', 'array', 'max:200'],
            'order_ids.*' => ['uuid', 'distinct'],
        ], [], ['gateway' => __('ödeme yöntemi'), 'amount' => __('tutar'), 'order_ids' => __('siparişler'), 'order_ids.*' => __('sipariş')]);

        $gateways = $payments->balanceGateways(PaymentContext::for($request->user(), (string) $data['amount'], Money::base(), PaymentContext::PURPOSE_BALANCE));
        $gateway = $gateways[$data['gateway']] ?? null;

        if ($gateway === null) {
            throw new ShopException(__('Seçilen ödeme yöntemi şu anda kullanılamıyor.'));
        }

        $start = $gateway->startBalancePayment($account, $request->user(), Money::round((string) $data['amount']), $data['order_ids'] ?? []);

        return redirect()->to((string) $start->redirectUrl);
    }

    /**
     * Havale bildirimi: tutar, tarih, hesap, (isteğe bağlı) sipariş ve dekont.
     */
    public function notify(Request $request, PaymentNotificationService $notifications): RedirectResponse
    {
        $account = $request->user()?->account;
        abort_if($account === null, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('status', Status::Active->value)],
            'order_id' => ['nullable', 'uuid'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [], [
            'amount' => __('tutar'), 'transfer_date' => __('havale tarihi'), 'bank_account_id' => __('banka hesabı'),
            'order_id' => __('sipariş'), 'sender_name' => __('gönderen'), 'description' => __('açıklama'), 'receipt' => __('dekont'),
        ]);

        $notifications->submit($account, $request->user(), $data, $request->file('receipt'));

        return back()->with('toast', ['type' => 'success', 'message' => __('Ödeme bildiriminiz alındı; onaylanınca carinize işlenecek.')]);
    }
}
