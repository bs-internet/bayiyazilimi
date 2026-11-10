<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\OrderPlacedEvent;

class LogOrderPlaced
{
    public function handle(OrderPlacedEvent $event): void
    {
        $order = $event->order;

        activity('order')
            ->performedOn($order)
            ->event('created')
            ->log(__(':customer_name, :number numaralı siparişi oluşturdu.', ['customer_name' => $order->customer_name, 'number' => $order->number]));
    }
}
