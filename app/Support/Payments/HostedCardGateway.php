<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sağlayıcının güvenli ödeme sayfasıyla (iFrame / Checkout Form, 3D Secure)
 * çalışan kart sürücüleri için ortak akış:
 *
 * 1. `start()` bekleyen bir ödeme kaydı açar ve müşteriyi imzalı
 *    `/odeme/kart/{ref}` sayfasına yönlendirir (sipariş transaction'ı içinde HTTP yok).
 * 2. `renderPayment()` sağlayıcıdan oturum / token alır ve ödeme sayfasını gösterir.
 * 3. Sağlayıcı bildirimi / dönüşü `complete()` ya da `fail()` ile sonuçlanır:
 *    siparişse sipariş "Ödendi" (OrderService::markPaid), cari tahsilatsa ödeme + cari alacak.
 *
 * Kart bilgisi sistemde tutulmaz.
 */
abstract class HostedCardGateway extends BaseGateway implements AcceptsBalancePayments, HandlesCallbacks, ProvidesInstallments, RefundsPayments
{
    /** Sağlayıcının ödeme sayfasını gösterir (token alıp iFrame ya da yönlendirme). */
    abstract public function renderPayment(Payment $payment, Request $request): Response;

    /** Sürücü ayarlarda açık ve mağaza bilgileri girilmiş mi. */
    abstract protected function configured(): bool;

    public function icon(): string
    {
        return 'creditCard';
    }

    public function available(PaymentContext $context): bool
    {
        return $this->configured();
    }

    /** Yarım kalan kart ödemesi 60 dakika sonra başarısız sayılır. */
    public function pendingTimeout(): ?int
    {
        return 60;
    }

    public function start(Order $order): PaymentStart
    {
        $payment = $order->payments()->create([
            'account_id' => $order->account_id,
            'method' => $this->key(),
            'provider' => $this->key(),
            'merchant_oid' => Payment::newReference('K'),
            'amount' => $order->grand_total,
            'currency_code' => $order->currency_code,
            'status' => PaymentStatus::Pending,
            'user_id' => $order->user_id,
        ]);

        return PaymentStart::redirect(self::payUrl($payment));
    }

    public function startBalancePayment(Account $account, User $user, BigDecimal $amount, array $orderIds = []): PaymentStart
    {
        $payment = DB::transaction(function () use ($account, $user, $amount, $orderIds): Payment {
            $payment = Payment::query()->withoutGlobalScopes()->create([
                'account_id' => $account->id,
                'order_id' => null,
                'method' => $this->key(),
                'provider' => $this->key(),
                'merchant_oid' => Payment::newReference('K'),
                'amount' => Money::round($amount),
                'currency_code' => Money::baseCode(),
                'status' => PaymentStatus::Pending,
                'note' => __('Cari tahsilat (kart)'),
                'user_id' => $user->id,
            ]);

            app(LedgerService::class)->targetOrders($payment, $orderIds);

            return $payment;
        });

        return PaymentStart::redirect(self::payUrl($payment));
    }

    /** İmzalı, 2 saat geçerli ödeme sayfası adresi. */
    public static function payUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('shop.payment.pay', now()->addHours(2), ['reference' => $payment->merchant_oid]);
    }

    /**
     * Ödeme başarılı (sağlayıcı doğrulandıktan sonra). Aynı ödeme ikinci kez
     * gelirse bir şey yapmaz. Müşterinin ödediği fazlası (taksit / vade farkı) `fee_amount`.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function complete(Payment $payment, BigNumber|string $paidAmount, int $installment, array $payload): void
    {
        DB::transaction(function () use ($payment, $paidAmount, $installment, $payload) {
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->find($payment->id);

            // Yalnız sonuçlanmamış ya da zaman aşımına düşmüş ödeme tamamlanır: iade edilmiş /
            // iptal edilmiş ödemeye gelen yinelenen bildirim onu yeniden açmaz.
            if ($payment === null || ! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
                return;
            }

            $payment->forceFill([
                'installment' => max(1, $installment),
                'fee_amount' => Money::max(0, Money::sub($paidAmount, $payment->amount)),
                'payload' => [...($payment->payload ?? []), ...$payload],
                'paid_at' => now(),
            ])->save();

            $order = $payment->order_id !== null ? Order::query()->withoutGlobalScopes()->find($payment->order_id) : null;

            if ($order !== null) {
                app(OrderService::class)->markPaid($order, null, $installment > 1
                    ? __('Kartla ödendi (:method, :count taksit).', ['method' => $this->label(), 'count' => $installment])
                    : __('Kartla ödendi (:method).', ['method' => $this->label()]), $payment);

                return;
            }

            $payment->forceFill(['status' => PaymentStatus::Paid])->save();
            app(LedgerService::class)->recordPayment($payment);
        }, 3);
    }

    /**
     * Ödeme başarısız: sipariş ödemesiyse sipariş "Ödeme Başarısız" (stok geri).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function fail(Payment $payment, string $reason, array $payload = []): void
    {
        // complete() gibi kilitli: aynı anda gelen başarı bildirimi ödenmiş ödemeyi başarısıza çekmesin.
        DB::transaction(function () use ($payment, $reason, $payload) {
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->find($payment->id);

            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->forceFill(['payload' => [...($payment->payload ?? []), ...$payload]])->save();
            $order = $payment->order_id !== null ? Order::query()->withoutGlobalScopes()->find($payment->order_id) : null;

            if ($order !== null && $order->status === OrderStatus::Pending) {
                app(OrderService::class)->markPaymentFailed($order, $reason);

                return;
            }

            $payment->forceFill(['status' => PaymentStatus::Failed, 'failed_reason' => mb_substr($reason, 0, 250)])->save();
        }, 3);
    }

    /**
     * Müşterinin döneceği sayfa: siparişin sayfası (bayi / vitrin) ya da Cari Hesabım.
     */
    protected function finish(?Payment $payment): RedirectResponse
    {
        if ($payment === null) {
            return redirect()->route('home')->with('toast', ['type' => 'error', 'message' => __('Ödeme bulunamadı.')]);
        }

        $payment->refresh();
        $order = $payment->order_id !== null ? Order::query()->withoutGlobalScopes()->find($payment->order_id) : null;
        $target = $order !== null ? $order->customerUrl() : route('app.shop.payments.index');

        $toast = match ($payment->status) {
            PaymentStatus::Paid => ['type' => 'success', 'message' => $order !== null ? __('Ödemeniz alındı. Sipariş numaranız: :number', ['number' => $order->number]) : __('Ödemeniz alındı ve carinize işlendi.')],
            PaymentStatus::Pending => ['type' => 'info', 'message' => __('Ödemeniz işleniyor; birkaç dakika içinde sonuçlanacak.')],
            default => ['type' => 'error', 'message' => $payment->failed_reason
                ? __('Ödeme tamamlanamadı: :reason', ['reason' => $payment->failed_reason])
                : __('Ödeme tamamlanamadı.')],
        };

        return redirect()->to($target)->with('toast', $toast);
    }

    /**
     * Sağlayıcıya gönderilecek alıcı bilgisi (veri; arayüz metni değil, çevrilmez): siparişten (fatura adresi) ya da
     * cari tahsilatta ödemeyi başlatan kullanıcı / hesaptan.
     *
     * @return array{name: string, email: string, phone: string, address: string, city: string, description: string}
     */
    protected function buyer(Payment $payment): array
    {
        $order = $payment->order_id !== null ? Order::query()->withoutGlobalScopes()->find($payment->order_id) : null;

        if ($order !== null) {
            $billing = (array) $order->billing_address;

            return [
                'name' => (string) ($billing['name'] ?? $order->customer_name),
                'email' => (string) $order->email,
                'phone' => (string) ($order->phone ?: '05000000000'),
                'address' => trim(($billing['address'] ?? '').' '.($billing['district'] ?? '').' '.($billing['city'] ?? '')) ?: 'Türkiye',
                'city' => (string) ($billing['city'] ?? 'İstanbul'),
                'description' => "Sipariş {$order->number}",
            ];
        }

        $user = $payment->user_id !== null ? User::query()->find($payment->user_id) : null;
        $account = $payment->account_id !== null ? Account::query()->find($payment->account_id) : null;

        return [
            'name' => (string) ($user?->name ?? $account?->title ?? 'Müşteri'),
            'email' => (string) ($user?->email ?? $account?->invoice_email ?? 'musteri@example.com'),
            'phone' => '05000000000',
            'address' => 'Türkiye',
            'city' => 'İstanbul',
            'description' => 'Cari tahsilat'.($account?->title ? " — {$account->title}" : ''),
        ];
    }

    protected function paymentByReference(?string $reference): ?Payment
    {
        return $reference ? Payment::query()->withoutGlobalScopes()->where('merchant_oid', $reference)->first() : null;
    }
}
