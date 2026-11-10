<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Members;

use App\Concerns\LogsActivity;
use App\Events\Panel\Members\MemberEmailChangeRequestedEvent;

class LogMemberEmailChangeRequested
{
    use LogsActivity;

    public function handle(MemberEmailChangeRequestedEvent $event): void
    {
        $causerName = $event->causer->name;
        $memberLabel = $event->user->name !== '' ? $event->user->name : $event->user->email;

        $this->logActivity(
            logName: 'panel.member',
            subject: $event->user,
            causer: $event->causer,
            event: 'email_change_requested',
            message: __(':causer_name, :member_label üyesinin e-posta adresini :email → :email2 olarak değiştirmek için talep oluşturdu.', ['causer_name' => $causerName, 'member_label' => $memberLabel, 'email' => $event->user->email, 'email2' => $event->email]),
            properties: [
                'user_id' => $event->user->id,
                'old_email' => $event->user->email,
                'new_email' => $event->email,
            ],
        );
    }
}
