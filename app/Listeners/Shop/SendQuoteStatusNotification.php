<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Enums\Shop\QuoteStatus;
use App\Events\Shop\QuoteStatusChangedEvent;
use App\Notifications\Shop\QuoteResponseAdminNotification;
use App\Notifications\Shop\QuoteStatusNotification;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;

/**
 * Teklif e-postaları:
 * - Yönetici gönderdi / iptal etti → müşteriye.
 * - Müşteri onayladı / revize istedi / reddetti / geri çekti → mağazaya
 *   (sipariş bildirim adresi) ve teyit olarak müşteriye.
 * Talep alındı e-postaları SendQuoteRequestedNotifications'da; siparişe
 * dönüşünce sipariş e-postaları gider.
 */
class SendQuoteStatusNotification
{
    public function handle(QuoteStatusChangedEvent $event): void
    {
        $quote = $event->quote;

        if ($event->byCustomer) {
            $admin = ShopSettings::get('order_notification_email');

            if ($admin) {
                Notification::route('mail', $admin)->notify(new QuoteResponseAdminNotification($quote, $event->note));
            }
        }

        if ($quote->status === QuoteStatus::Ordered || blank($quote->email)) {
            return;
        }

        if ($event->byCustomer) {
            Notification::route('mail', $quote->email)->notify(new QuoteStatusNotification($quote, null, byCustomer: true));

            return;
        }

        if (in_array($quote->status, [QuoteStatus::Sent, QuoteStatus::Cancelled], true)) {
            Notification::route('mail', $quote->email)->notify(new QuoteStatusNotification($quote, $event->note));
        }
    }
}
