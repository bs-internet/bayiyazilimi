<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\OrderPaidEvent;

class LogOrderPaid
{
    use LogsActivity;

    public function handle(OrderPaidEvent $event): void
    {
        $order = $event->order;

        if ($event->causer !== null) {
            $this->logActivity(
                logName: 'order',
                subject: $order,
                causer: $event->causer,
                event: 'paid',
                message: __(':name, :number numaralı siparişin ödemesini onayladı.', ['name' => $event->causer->name, 'number' => $order->number]),
            );

            return;
        }

        activity('order')
            ->performedOn($order)
            ->event('paid')
            ->log(__(':number numaralı siparişin ödemesi alındı.', ['number' => $order->number]));
    }
}
