<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Roles;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Roles\RolePermissionsSyncedEvent;

class LogRolePermissionsSynced
{
    use LogsActivity;

    public function handle(RolePermissionsSyncedEvent $event): void
    {
        $causerName = $event->causer->name;
        $roleName = $event->role->name;
        $addedCount = \count($event->added);
        $removedCount = \count($event->removed);

        $this->logActivity(
            logName: 'panel.role',
            subject: $event->role,
            causer: $event->causer,
            event: 'permissions_synced',
            message: __(':causer_name, :role_name rolünün izinlerini güncelledi (:added_count eklendi, :removed_count kaldırıldı).', ['causer_name' => $causerName, 'role_name' => $roleName, 'added_count' => $addedCount, 'removed_count' => $removedCount]),
            properties: [
                'role_uuid' => $event->role->getKey(),
                'role_name' => $event->role->name,
                'added' => $event->added,
                'removed' => $event->removed,
            ],
        );
    }
}
