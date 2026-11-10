<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RefundStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Events\Shop\PaymentRefundedEvent;
use App\Events\Shop\PaymentRefundFailedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Support\Money;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\RefundsPayments;
use Brick\Math\BigNumber;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sağlayıcı üzerinden para iadesi (kart). Üç adım:
 *
 * 1. Ödeme kilitliyken iade kaydı "İşlemde" yazılır ve commit edilir (işlemdeki
 *    iade iade edilebilir tutardan düşer: eşzamanlı iki iade fazla iade yapamaz).
 * 2. Sağlayıcı transaction DIŞINDA çağrılır; reddederse (ShopException) kayıt
 *    "Başarısız" olur, sonuç bilinmiyorsa (bağlantı hatası) "İşlemde" kalır.
 * 3. Başarılıysa kayıt "Tamamlandı" olur ve cari / ödeme / sipariş işlenir.
 *    Bu adım çökerse kayıt "İşlemde" kalır ve tekrar iade edilemez (para iki kez
 *    iade edilmez); yönetici sağlayıcı panelinden doğrulayıp sipariş detayından
 *    sonuçlandırır: `confirm()` (iade yapılmış) ya da `abandon()` (yapılmamış).
 *
 * Tamamı tek seferde iade edilirse ödemenin alacağı ters kayıtla kapanır; kısmi
 * iadede iade tutarı cariye "para iadesi" borcu olarak yazılır.
 */
class PaymentRefundService
{
    /**
     * "İşlemde" iade ancak bu kadar dakika sonra elle sonuçlandırılabilir
     * (sağlayıcı çağrısı sürerken araya girilmesin).
     */
    public const STALE_AFTER_MINUTES = 5;

    public function __construct(
        private readonly PaymentRegistry $payments,
        private readonly LedgerService $ledger,
    ) {}

    public function refund(Payment $payment, BigNumber|int|string $amount, User $causer): void
    {
        $gateway = $this->payments->get($payment->method);

        if (! $gateway instanceof RefundsPayments) {
            throw new ShopException(__('Bu ödeme yöntemi sağlayıcı üzerinden iadeyi desteklemiyor.'));
        }

        $amount = Money::round($amount);
        $refund = $this->reserve($payment, $amount, $causer);

        try {
            $gateway->refund($payment, $amount);
        } catch (ShopException $e) {
            // Sağlayıcı iadeyi reddetti.
            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failed_reason' => mb_substr($e->getMessage(), 0, 250),
                'completed_at' => now(),
            ])->save();

            PaymentRefundFailedEvent::dispatch($refund, $causer);

            throw $e;
        } catch (Throwable $e) {
            // Sonuç bilinmiyor (zaman aşımı, bağlantı kopması): iade yapılmış olabilir.
            // Kayıt "İşlemde" kalır, yönetici sağlayıcı panelinden doğrulayıp sonuçlandırır.
            report($e);

            throw new ShopException(__('İadenin sonucu alınamadı. Sağlayıcı panelinden kontrol edip :minutes dakika sonra Ödemeler kartından sonuçlandırın.', ['minutes' => self::STALE_AFTER_MINUTES]));
        }

        $this->settle($payment, $refund, $causer);

        PaymentRefundedEvent::dispatch($payment->fresh(), $amount, $causer);
    }

    /**
     * "İşlemde" kalmış iade sağlayıcıda yapılmış (yönetici sağlayıcı panelinden
     * doğruladı): kayıt tamamlanır, cari / ödeme / sipariş işlenir.
     */
    public function confirm(PaymentRefund $refund, User $causer): void
    {
        $this->ensureStale($refund);

        $payment = Payment::query()->withoutGlobalScopes()->findOrFail($refund->payment_id);

        $this->settle($payment, $refund, $causer);

        PaymentRefundedEvent::dispatch($payment->fresh(), Money::decimal($refund->amount), $causer);
    }

    /**
     * "İşlemde" kalmış iade sağlayıcıda yapılmamış: kayıt "Başarısız" olur ve
     * ayrılan tutar yeniden iade edilebilir hale gelir. Cariye dokunulmaz.
     */
    public function abandon(PaymentRefund $refund, User $causer): void
    {
        $this->ensureStale($refund);

        DB::transaction(function () use ($refund) {
            $payment = Payment::query()->withoutGlobalScopes()->findOrFail($refund->payment_id);
            $this->lock($payment);
            $this->lockRefund($refund);

            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failed_reason' => __('Yönetici: iade sağlayıcıda yapılmamış.'),
                'completed_at' => now(),
            ])->save();
        });

        PaymentRefundFailedEvent::dispatch($refund->fresh(), $causer);
    }

    /** Elle sonuçlandırma yalnız bir süredir "İşlemde" bekleyen iade için. */
    private function ensureStale(PaymentRefund $refund): void
    {
        if ($refund->status !== RefundStatus::Pending) {
            throw new ShopException(__('Bu iade zaten sonuçlanmış.'));
        }

        if ($refund->created_at !== null && $refund->created_at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            throw new ShopException(__('İade hâlâ sağlayıcıda işleniyor olabilir; :minutes dakika sonra yeniden deneyin.', ['minutes' => self::STALE_AFTER_MINUTES]));
        }
    }

    /**
     * 1. adım: kilitli ödeme üzerinde tutar denetlenir, "İşlemde" iade kaydı yazılır.
     */
    private function reserve(Payment $payment, BigNumber $amount, User $causer): PaymentRefund
    {
        return DB::transaction(function () use ($payment, $amount, $causer): PaymentRefund {
            $this->lock($payment);

            if ($payment->status !== PaymentStatus::Paid) {
                throw new ShopException(__('Yalnız ödenmiş bir ödeme iade edilebilir.'));
            }

            $code = $payment->currency_code;
            $remaining = Money::of($payment->amount, $code)->minus(Money::of($payment->refundedAmount(), $code));
            $requested = Money::of($amount, $code);

            if (! $requested->isPositive() || $requested->isGreaterThan($remaining)) {
                throw new ShopException(__('İade tutarı en fazla :amount :currency olabilir.', ['amount' => Money::number($remaining->getAmount()), 'currency' => $code]));
            }

            return $payment->refunds()->create([
                'amount' => Money::round($amount),
                'currency_code' => $code,
                'status' => RefundStatus::Pending,
                'user_id' => $causer->id,
            ]);
        });
    }

    /**
     * 3. adım: sağlayıcı iadeyi yaptı; kayıt tamamlanır, cari / ödeme / sipariş işlenir.
     */
    private function settle(Payment $payment, PaymentRefund $refund, User $causer): void
    {
        DB::transaction(function () use ($payment, $refund, $causer) {
            $this->lock($payment);
            $this->lockRefund($refund);

            $code = $payment->currency_code;
            $paid = Money::of($payment->amount, $code);
            $earlier = PaymentRefund::query()->where('payment_id', $payment->id)->whereKeyNot($refund->id)
                ->where('status', RefundStatus::Succeeded)->pluck('amount')->all();
            $refundedBefore = Money::of(Money::add(...$earlier), $code);
            $amount = Money::of($refund->amount, $code);

            $refund->forceFill(['status' => RefundStatus::Succeeded, 'completed_at' => now()])->save();

            // Tamamı tek seferde: ödemenin alacağı ters kayıtla kapanır; parça parça: her iade borç yazılır.
            $wholeAtOnce = $refundedBefore->isZero() && $amount->isEqualTo($paid);
            $full = $refundedBefore->plus($amount)->isGreaterThanOrEqualTo($paid);

            if ($full) {
                $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
            }

            $accountId = $payment->account_id ?? $payment->order?->account_id;

            if ($wholeAtOnce) {
                $this->ledger->reversePayment($payment, $causer, __('Kart iadesi: :method', ['method' => $payment->methodLabel()]));
            } elseif ($accountId !== null) {
                $base = $payment->order !== null && $payment->order->currency_code === $code
                    ? $this->ledger->orderAmount($payment->order, $refund->amount)
                    : Money::toBase($refund->amount, $payment->currency());

                $this->ledger->record($accountId, TransactionType::Refund, TransactionDirection::Debit, $base, [
                    'source' => $payment,
                    'original_amount' => $refund->amount,
                    'original_currency' => $code,
                    'description' => __('Kısmi kart iadesi: :method', ['method' => $payment->methodLabel()]),
                    'user' => $causer,
                ]);
            }

            $order = $payment->order_id !== null ? Order::query()->withoutGlobalScopes()->lockForUpdate()->find($payment->order_id) : null;

            if ($order === null) {
                return;
            }

            // Sipariş ancak ödenmiş başka ödemesi kalmadıysa "İade Edildi" olur.
            $otherPaid = $order->payments()->whereKeyNot($payment->id)->where('status', PaymentStatus::Paid)->exists();

            if ($full && ! $otherPaid && in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Partial], true)) {
                $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
            }

            $order->histories()->create([
                'status' => $order->status,
                'note' => $full
                    ? __('Ödeme kart sağlayıcısı üzerinden iade edildi: :amount :currency.', ['amount' => Money::number($refund->amount), 'currency' => $code])
                    : __('Ödemenin bir kısmı kart sağlayıcısı üzerinden iade edildi: :amount :currency.', ['amount' => Money::number($refund->amount), 'currency' => $code]),
                'user_id' => $causer->id,
            ]);
        });
    }

    /** Transaction içinde: iade kaydını kilitler; yalnız "İşlemde" olan sonuçlandırılır. */
    private function lockRefund(PaymentRefund $refund): void
    {
        $locked = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
        $refund->setRawAttributes($locked->getAttributes(), true);

        if ($refund->status !== RefundStatus::Pending) {
            throw new ShopException(__('Bu iade zaten sonuçlanmış.'));
        }
    }

    /** Transaction içinde: ödemeyi kilitleyip güncel haliyle yeniden okur. */
    private function lock(Payment $payment): void
    {
        $locked = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
        $payment->setRawAttributes($locked->getAttributes(), true);
    }
}
