<?php

declare(strict_types=1);

namespace App\Listeners\Panel\System;

use App\Concerns\LogsActivity;
use App\Events\Panel\System\SystemUpdatedEvent;

class LogSystemUpdated
{
    use LogsActivity;

    public function handle(SystemUpdatedEvent $event): void
    {
        $this->logActivity(
            logName: 'panel.system',
            causer: $event->causer,
            event: 'updated',
            message: __(':causer_name, sistemi :version sürümüne güncelledi.', ['causer_name' => $event->causer->name, 'version' => $event->version]),
            properties: [
                'version' => $event->version,
                'steps' => $event->steps,
            ],
        );
    }
}
