<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\OrderStatusChangedEvent;

class LogOrderStatusChanged
{
    use LogsActivity;

    public function handle(OrderStatusChangedEvent $event): void
    {
        $this->logActivity(
            logName: 'order',
            subject: $event->order,
            causer: $event->causer,
            event: 'status',
            message: __(':name, :number numaralı siparişin durumunu \':event\' → \':event2\' olarak değiştirdi.', ['name' => $event->causer->name, 'number' => $event->order->number, 'event' => $event->previous->label(), 'event2' => $event->order->status->label()]),
        );
    }
}
