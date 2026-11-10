<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Permissions;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Permissions\PermissionForceDeletedEvent;

class LogPermissionForceDeleted
{
    use LogsActivity;

    public function handle(PermissionForceDeletedEvent $event): void
    {
        $causerName = $event->causer->name;

        $this->logActivity(
            logName: 'panel.permission',
            causer: $event->causer,
            event: 'force_deleted',
            message: __(':causer_name, \':name\' iznini kalıcı olarak sildi.', ['causer_name' => $causerName, 'name' => $event->name]),
            properties: ['permission_name' => $event->name],
        );
    }
}
