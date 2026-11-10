<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\OrderPlacedEvent;
use App\Notifications\Shop\NewOrderAdminNotification;
use App\Notifications\Shop\OrderReceivedNotification;
use App\Services\Shop\ShopSettings;
use App\Support\Payments\PaymentRegistry;
use Illuminate\Support\Facades\Notification;

class SendOrderPlacedNotifications
{
    public function handle(OrderPlacedEvent $event): void
    {
        $order = $event->order;

        Notification::route('mail', $order->email)->notify(new OrderReceivedNotification($order));

        // Ödemesi sağlayıcının sayfasında tamamlanan (kart) siparişler yöneticiye
        // ödeme alındığında bir kez bildirilir (SendOrderPaidNotification);
        // yarım kalan kart ödemeleri yöneticiye e-posta üretmez.
        if (PaymentRegistry::paysOnProviderPage($order->payment_method)) {
            return;
        }

        $admin = ShopSettings::get('order_notification_email');
        if ($admin) {
            Notification::route('mail', $admin)->notify(new NewOrderAdminNotification($order));
        }
    }
}
