<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Roles;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Roles\UserRoleAssignedEvent;

class LogUserRoleAssigned
{
    use LogsActivity;

    public function handle(UserRoleAssignedEvent $event): void
    {
        $causerName = $event->causer->name;
        $userLabel = $event->user->name !== '' ? $event->user->name : $event->user->email;
        $message = $event->previousRoleName !== null
            ? __(':causer_name, :user_label kullanıcısının rolünü :previous_role_name → :role_name olarak değiştirdi.', ['causer_name' => $causerName, 'user_label' => $userLabel, 'previous_role_name' => $event->previousRoleName, 'role_name' => $event->roleName])
            : __(':causer_name, :user_label kullanıcısına :role_name rolünü atadı.', ['causer_name' => $causerName, 'user_label' => $userLabel, 'role_name' => $event->roleName]);

        $this->logActivity(
            logName: 'panel.role',
            subject: $event->user,
            causer: $event->causer,
            event: 'role_assigned',
            message: $message,
            properties: [
                'user_id' => $event->user->getKey(),
                'role_name' => $event->roleName,
                'previous_role_name' => $event->previousRoleName,
            ],
        );
    }
}
