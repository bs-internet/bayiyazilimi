<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\QuoteRequestedEvent;
use App\Notifications\Shop\NewQuoteAdminNotification;
use App\Notifications\Shop\QuoteReceivedNotification;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;

class SendQuoteRequestedNotifications
{
    public function handle(QuoteRequestedEvent $event): void
    {
        $quote = $event->quote;

        Notification::route('mail', $quote->email)->notify(new QuoteReceivedNotification($quote));

        $admin = ShopSettings::get('order_notification_email');
        if ($admin) {
            Notification::route('mail', $admin)->notify(new NewQuoteAdminNotification($quote));
        }
    }
}
