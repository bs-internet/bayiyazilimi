<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\ReturnRequestedEvent;
use App\Notifications\Shop\NewReturnAdminNotification;
use App\Notifications\Shop\ReturnReceivedNotification;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;

class SendReturnRequestedNotifications
{
    public function handle(ReturnRequestedEvent $event): void
    {
        $return = $event->returnRequest->loadMissing('order');

        Notification::route('mail', $return->order->email)->notify(new ReturnReceivedNotification($return));

        $admin = ShopSettings::get('order_notification_email');
        if ($admin) {
            Notification::route('mail', $admin)->notify(new NewReturnAdminNotification($return));
        }
    }
}
