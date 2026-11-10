<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\CatalogChangedEvent;

class LogCatalogChanged
{
    use LogsActivity;

    public function handle(CatalogChangedEvent $event): void
    {
        $this->logActivity(
            logName: $event->logName,
            subject: $event->subject,
            causer: $event->causer,
            event: $event->action,
            message: __(':name, :message', ['name' => $event->causer->name, 'message' => $event->message]),
        );
    }
}
