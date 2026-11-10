<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\OrderPaidEvent;
use App\Notifications\Shop\NewOrderAdminNotification;
use App\Notifications\Shop\OrderStatusNotification;
use App\Services\Shop\ShopSettings;
use App\Support\Payments\PaymentRegistry;
use Illuminate\Support\Facades\Notification;

class SendOrderPaidNotification
{
    public function handle(OrderPaidEvent $event): void
    {
        $order = $event->order;

        Notification::route('mail', $order->email)->notify(new OrderStatusNotification($order, __('Ödemeniz alındı, siparişiniz hazırlanıyor.')));

        // Kart siparişi yöneticiye sipariş anında değil, ödeme tamamlanınca bir kez
        // bildirilir (SendOrderPlacedNotifications). Diğer yöntemlerde sipariş
        // zaten bildirildiği için tekrar gönderilmez.
        $admin = ShopSettings::get('order_notification_email');
        if ($admin && $event->causer === null && PaymentRegistry::paysOnProviderPage($order->payment_method)) {
            Notification::route('mail', $admin)->notify(new NewOrderAdminNotification($order));
        }
    }
}
