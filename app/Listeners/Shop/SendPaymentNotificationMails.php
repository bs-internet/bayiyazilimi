<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\PaymentNotificationReviewedEvent;
use App\Events\Shop\PaymentNotificationSubmittedEvent;
use App\Notifications\Shop\NewPaymentNotificationAdminNotification;
use App\Notifications\Shop\PaymentNotificationReviewedNotification;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;

/**
 * Havale bildirimi e-postaları: yeni bildirim → sipariş bildirim adresine;
 * onay / ret → bildirimi yapan bayi kullanıcısına.
 */
class SendPaymentNotificationMails
{
    public function handleSubmitted(PaymentNotificationSubmittedEvent $event): void
    {
        $admin = ShopSettings::get('order_notification_email');

        if ($admin) {
            Notification::route('mail', $admin)->notify(new NewPaymentNotificationAdminNotification($event->notification));
        }
    }

    public function handleReviewed(PaymentNotificationReviewedEvent $event): void
    {
        $event->notification->user?->notify(new PaymentNotificationReviewedNotification($event->notification));
    }
}
