<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Members;

use App\Concerns\LogsActivity;
use App\Events\Panel\Members\MemberStatusUpdatedEvent;

class LogMemberStatusUpdated
{
    use LogsActivity;

    public function handle(MemberStatusUpdatedEvent $event): void
    {
        $causerName = $event->causer->name;
        $memberLabel = $event->user->name !== '' ? $event->user->name : $event->user->email;
        $oldLabel = $event->oldStatus->value;
        $newLabel = $event->newStatus->value;

        $this->logActivity(
            logName: 'panel.member',
            subject: $event->user,
            causer: $event->causer,
            event: 'status_updated',
            message: __(':causer_name, :member_label üyesinin durumunu :old_label → :new_label olarak değiştirdi.', ['causer_name' => $causerName, 'member_label' => $memberLabel, 'old_label' => $oldLabel, 'new_label' => $newLabel]),
            properties: [
                'user_id' => $event->user->id,
                'old_status' => $event->oldStatus->value,
                'new_status' => $event->newStatus->value,
            ],
        );
    }
}
