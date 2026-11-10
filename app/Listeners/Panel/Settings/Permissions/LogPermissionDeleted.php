<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Permissions;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Permissions\PermissionDeletedEvent;

class LogPermissionDeleted
{
    use LogsActivity;

    public function handle(PermissionDeletedEvent $event): void
    {
        $causerName = $event->causer->name;

        $this->logActivity(
            logName: 'panel.permission',
            subject: $event->permission,
            causer: $event->causer,
            event: 'deleted',
            message: __(':causer_name, \':name\' iznini sildi (geri yüklenebilir).', ['causer_name' => $causerName, 'name' => $event->permission->name]),
        );
    }
}
