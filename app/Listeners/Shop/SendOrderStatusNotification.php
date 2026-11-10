<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\OrderStatusChangedEvent;
use App\Notifications\Shop\OrderStatusNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Sipariş durumu her değiştiğinde müşteriye e-posta gider. Yöneticinin notu
 * yalnız "notu müşteriye göster" seçiliyse e-postaya eklenir.
 */
class SendOrderStatusNotification
{
    public function handle(OrderStatusChangedEvent $event): void
    {
        if ($event->previous === $event->order->status) {
            return;
        }

        Notification::route('mail', $event->order->email)
            ->notify(new OrderStatusNotification($event->order, $event->notify ? $event->note : null));
    }
}
