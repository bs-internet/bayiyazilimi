<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\ReturnRequestedEvent;

class LogReturnRequested
{
    use LogsActivity;

    public function handle(ReturnRequestedEvent $event): void
    {
        $return = $event->returnRequest->loadMissing(['order', 'user']);

        if ($return->user === null) {
            return;
        }

        $this->logActivity(
            logName: 'return',
            subject: $return,
            causer: $return->user,
            event: 'created',
            message: __(':name, :number numaralı sipariş için :number2 iade talebini oluşturdu.', ['name' => $return->user->name, 'number' => $return->order->number, 'number2' => $return->number]),
        );
    }
}
