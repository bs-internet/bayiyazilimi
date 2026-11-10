<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\ReturnStatusChangedEvent;

class LogReturnStatusChanged
{
    use LogsActivity;

    public function handle(ReturnStatusChangedEvent $event): void
    {
        $return = $event->returnRequest;

        $this->logActivity(
            logName: 'return',
            subject: $return,
            causer: $event->causer,
            event: 'status',
            message: __(':name, :number iade talebinin durumunu \':event\' → \':return\' olarak değiştirdi.', ['name' => $event->causer->name, 'number' => $return->number, 'event' => $event->previous->label(), 'return' => $return->status->label()]),
        );
    }
}
