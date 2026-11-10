<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Enums\Shop\PaymentNotificationStatus;
use App\Mail\Shop\PaymentNotificationReviewedMail;
use App\Models\PaymentNotification;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Bayiye: havale bildirimi onaylandı / reddedildi (e-posta + uygulama içi).
 */
class PaymentNotificationReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PaymentNotification $paymentNotification,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): PaymentNotificationReviewedMail
    {
        return (new PaymentNotificationReviewedMail($this->paymentNotification))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approved = $this->paymentNotification->status === PaymentNotificationStatus::Approved;
        $amount = Money::format($this->paymentNotification->amount);

        return [
            'type' => 'shop.payment_notification_reviewed',
            'title' => $approved ? __('Havale bildiriminiz onaylandı') : __('Havale bildiriminiz reddedildi'),
            'message' => $approved
                ? __(':amount tutarındaki ödemeniz carinize işlendi.', ['amount' => $amount])
                : __(':amount tutarındaki bildiriminiz reddedildi: :reject_reason', ['amount' => $amount, 'reject_reason' => $this->paymentNotification->reject_reason]),
            'url' => route('app.shop.payments.index'),
        ];
    }
}
