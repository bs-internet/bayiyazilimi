<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\NewPaymentNotificationAdminMail;
use App\Models\PaymentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class NewPaymentNotificationAdminNotification extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): NewPaymentNotificationAdminMail
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->email;

        return (new NewPaymentNotificationAdminMail($this->paymentNotification))->to($to);
    }
}
