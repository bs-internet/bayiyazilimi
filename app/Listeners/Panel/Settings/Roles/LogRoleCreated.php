<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Roles;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Roles\RoleCreatedEvent;

class LogRoleCreated
{
    use LogsActivity;

    public function handle(RoleCreatedEvent $event): void
    {
        $causerName = $event->causer->name;
        $roleName = $event->role->name;

        $this->logActivity(
            logName: 'panel.role',
            subject: $event->role,
            causer: $event->causer,
            event: 'created',
            message: __(':causer_name, :role_name rolünü oluşturdu.', ['causer_name' => $causerName, 'role_name' => $roleName]),
            properties: [
                'role_uuid' => $event->role->getKey(),
                'role_name' => $event->role->name,
                'permissions' => $event->permissions,
            ],
        );
    }
}
