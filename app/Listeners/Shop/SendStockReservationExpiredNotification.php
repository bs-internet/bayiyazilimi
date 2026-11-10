<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Enums\Shop\OrderStatus;
use App\Events\Shop\StockReservationExpiredEvent;
use App\Notifications\Shop\OrderStatusNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Süresi dolan siparişin müşterisine durum e-postası (nedeniyle birlikte).
 */
class SendStockReservationExpiredNotification
{
    public function handle(StockReservationExpiredEvent $event): void
    {
        $note = $event->previous === OrderStatus::AwaitingApproval
            ? __('Siparişiniz :days gün içinde onaylanamadığı için iptal edildi. Dilerseniz yeniden sipariş verebilirsiniz.', ['days' => $event->days])
            : __('Ödemeniz :days gün içinde ulaşmadığı için siparişiniz kapatıldı. Ödeme yaptıysanız lütfen bizimle iletişime geçin.', ['days' => $event->days]);

        Notification::route('mail', $event->order->email)
            ->notify(new OrderStatusNotification($event->order, $note));
    }
}
