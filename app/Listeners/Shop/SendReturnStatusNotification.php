<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\ReturnStatusChangedEvent;
use App\Notifications\Shop\ReturnStatusNotification;
use Illuminate\Support\Facades\Notification;

class SendReturnStatusNotification
{
    public function handle(ReturnStatusChangedEvent $event): void
    {
        if ($event->previous === $event->returnRequest->status) {
            return;
        }

        $return = $event->returnRequest->loadMissing('order');

        Notification::route('mail', $return->order->email)->notify(new ReturnStatusNotification($return));
    }
}
