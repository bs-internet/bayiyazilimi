<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentNotificationStatus;
use App\Enums\Shop\PaymentStatus;
use App\Events\Shop\PaymentNotificationReviewedEvent;
use App\Events\Shop\PaymentNotificationSubmittedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\User;
use App\Services\Support\MediaService;
use App\Support\Money;
use App\Support\Payments\Gateways\BankTransferGateway;
use Brick\Math\BigNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Havale / EFT ödeme bildirimi. Onaylanınca bildirilen tutarda
 * ödeme kaydı yazılır: siparişe bağlıysa ve havale bekleyen siparişin tutarını
 * karşılıyorsa sipariş "Ödendi" olur; değilse tutar cariye alacak yazılır ve
 * FIFO ile (önce bağlı siparişe) dağıtılır.
 */
class PaymentNotificationService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly OrderService $orders,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array{order_id?: ?string, bank_account_id?: ?string, amount: float|string, transfer_date: string, sender_name?: ?string, description?: ?string}  $data
     */
    public function submit(Account $account, User $user, array $data, ?UploadedFile $receipt): PaymentNotification
    {
        $notification = DB::transaction(function () use ($account, $user, $data, $receipt) {
            $orderId = $data['order_id'] ?? null;

            if ($orderId !== null && ! Order::query()->withoutGlobalScopes()->whereKey($orderId)->where('account_id', $account->id)->exists()) {
                throw new ShopException(__('Seçilen sipariş bulunamadı.'));
            }

            $notification = PaymentNotification::query()->create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'order_id' => $orderId,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'amount' => Money::round((string) $data['amount']),
                'currency_code' => Money::baseCode(),
                'transfer_date' => $data['transfer_date'],
                'sender_name' => $data['sender_name'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => PaymentNotificationStatus::Pending,
            ]);

            if ($receipt !== null) {
                $this->media->attach($notification, $receipt, PaymentNotification::RECEIPT);
            }

            return $notification;
        });

        PaymentNotificationSubmittedEvent::dispatch($notification);

        return $notification;
    }

    /**
     * Onay: ödeme kaydı (havale) + cari alacak; tutar yönetici tarafından düzeltilebilir.
     */
    public function approve(PaymentNotification $notification, User $causer, BigNumber|int|string|null $amount = null): Payment
    {
        $this->ensurePending($notification);

        $payment = DB::transaction(function () use ($notification, $causer, $amount) {
            // Eşzamanlı iki onay aynı bildirimi iki kez cariye işlemesin: satır kilitli yeniden okunur.
            $this->lockPending($notification);

            $amount = Money::round($amount ?? $notification->amount);
            // Sipariş kilitli okunur: "siparişi kapatır mı" kararı kart ödemesi / yönetici işlemiyle yarışmasın.
            $order = $notification->order_id !== null ? Order::query()->withoutGlobalScopes()->lockForUpdate()->find($notification->order_id) : null;

            $payment = Payment::query()->create([
                'account_id' => $notification->account_id,
                'order_id' => $order?->id,
                'method' => BankTransferGateway::KEY,
                'provider' => 'notification',
                'merchant_oid' => Payment::newReference('H'),
                'amount' => $amount,
                'currency_code' => $notification->currency_code,
                'status' => PaymentStatus::Pending,
                'note' => trim(__('Ödeme bildirimi').($notification->sender_name ? ' · '.$notification->sender_name : '')),
                'user_id' => $causer->id,
                'paid_at' => $notification->transfer_date,
            ]);

            // Havale bekleyen sipariş ve tutar karşılıyorsa: sipariş ödendi (ödeme kaydı bu bildirim).
            $settlesOrder = $order !== null
                && $order->payment_status === PaymentStatus::Pending
                && in_array($order->status, [OrderStatus::Pending, OrderStatus::Failed], true)
                && $amount->isGreaterThanOrEqualTo($this->ledger->orderAmount($order, $order->grand_total));

            if ($settlesOrder) {
                $this->orders->markPaid($order, $causer, __('Havale bildirimi onaylandı (:date).', ['date' => $notification->transfer_date->format('d.m.Y')]), $payment);
            } else {
                $payment->forceFill(['status' => PaymentStatus::Paid])->save();
                $this->ledger->recordPayment($payment);
            }

            $notification->forceFill([
                'status' => PaymentNotificationStatus::Approved,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reviewed_by' => $causer->id,
                'reviewed_at' => now(),
            ])->save();

            return $payment;
        });

        PaymentNotificationReviewedEvent::dispatch($notification->fresh(), $causer);

        return $payment;
    }

    public function reject(PaymentNotification $notification, User $causer, string $reason): void
    {
        $this->ensurePending($notification);

        DB::transaction(function () use ($notification, $causer, $reason) {
            $this->lockPending($notification);

            $notification->forceFill([
                'status' => PaymentNotificationStatus::Rejected,
                'reject_reason' => $reason,
                'reviewed_by' => $causer->id,
                'reviewed_at' => now(),
            ])->save();
        });

        PaymentNotificationReviewedEvent::dispatch($notification->fresh(), $causer);
    }

    /**
     * Transaction içinde: bildirimi kilitleyip güncel durumuyla yeniden okur
     * (verilen modeli de günceller) ve hâlâ beklemede olduğunu doğrular.
     */
    private function lockPending(PaymentNotification $notification): void
    {
        $locked = PaymentNotification::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($notification->id);
        $notification->setRawAttributes($locked->getAttributes(), true);

        $this->ensurePending($notification);
    }

    private function ensurePending(PaymentNotification $notification): void
    {
        if ($notification->status !== PaymentNotificationStatus::Pending) {
            throw new ShopException(__('Bu ödeme bildirimi zaten sonuçlandırılmış.'));
        }
    }
}
