<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Roles;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Roles\RoleDeletedEvent;

class LogRoleDeleted
{
    use LogsActivity;

    public function handle(RoleDeletedEvent $event): void
    {
        $causerName = $event->causer->name;

        $this->logActivity(
            logName: 'panel.role',
            causer: $event->causer,
            event: 'deleted',
            message: __(':causer_name, :role_name rolünü sildi.', ['causer_name' => $causerName, 'role_name' => $event->roleName]),
            properties: [
                'role_name' => $event->roleName,
            ],
        );
    }
}
