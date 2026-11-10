<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\General;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\General\SettingsUpdatedEvent;

class LogSettingsUpdated
{
    use LogsActivity;

    public function handle(SettingsUpdatedEvent $event): void
    {
        $userName = $event->causer->name;

        $this->logActivity(
            logName: 'settings',
            causer: $event->causer,
            event: 'updated',
            message: __(':user_name, genel ayarları güncelledi.', ['user_name' => $userName]),
        );
    }
}
