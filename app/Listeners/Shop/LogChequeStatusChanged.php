<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\ChequeStatusChangedEvent;

class LogChequeStatusChanged
{
    use LogsActivity;

    public function handle(ChequeStatusChangedEvent $event): void
    {
        $cheque = $event->cheque;

        $this->logActivity(
            logName: 'cheque',
            subject: $cheque,
            causer: $event->causer,
            event: 'status',
            message: __(':name, :serial numaralı :type durumunu \':from\' → \':to\' olarak değiştirdi.', [
                'name' => $event->causer->name,
                'serial' => $cheque->serial_no,
                'type' => mb_strtolower($cheque->type->label()),
                'from' => $event->previous->label(),
                'to' => $cheque->status->label(),
            ]),
        );
    }
}
