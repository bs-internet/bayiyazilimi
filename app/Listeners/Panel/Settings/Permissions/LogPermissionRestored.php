<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Permissions;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Permissions\PermissionRestoredEvent;

class LogPermissionRestored
{
    use LogsActivity;

    public function handle(PermissionRestoredEvent $event): void
    {
        $causerName = $event->causer->name;

        $this->logActivity(
            logName: 'panel.permission',
            subject: $event->permission,
            causer: $event->causer,
            event: 'restored',
            message: __(':causer_name, \':name\' iznini geri yükledi.', ['causer_name' => $causerName, 'name' => $event->permission->name]),
        );
    }
}
