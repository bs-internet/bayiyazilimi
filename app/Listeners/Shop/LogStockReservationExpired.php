<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\StockReservationExpiredEvent;

class LogStockReservationExpired
{
    public function handle(StockReservationExpiredEvent $event): void
    {
        activity('order')
            ->performedOn($event->order)
            ->event('reservation_expired')
            ->withProperties(['days' => $event->days])
            ->log(__(':number numaralı siparişin stok rezervasyon süresi (:days gün) doldu: \':from\' → \':to\'.', [
                'number' => $event->order->number,
                'days' => $event->days,
                'from' => $event->previous->label(),
                'to' => $event->order->status->label(),
            ]));
    }
}
