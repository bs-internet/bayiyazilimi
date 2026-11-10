<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\UnappliedPaymentAdminMail;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UnappliedPaymentAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): UnappliedPaymentAdminMail
    {
        return (new UnappliedPaymentAdminMail($this->order, $this->payment, $this->reason))->to($notifiable->email);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'shop.unapplied_payment',
            'order_id' => $this->order->id,
            'title' => __(':number siparişine uygulanamayan ödeme', ['number' => $this->order->number]),
            'message' => __(':amount :currency ödeme alındı (:reason); siparişi yeniden açın ya da ödemeyi iade edin.', [
                'amount' => Money::number($this->payment->amount),
                'currency' => $this->payment->currency_code,
                'reason' => $this->reason,
            ]),
        ];
    }
}
