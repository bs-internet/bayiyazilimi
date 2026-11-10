<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Members;

use App\Concerns\LogsActivity;
use App\Events\Panel\Members\MemberWelcomeAcceptedEvent;

class LogMemberWelcomeAccepted
{
    use LogsActivity;

    public function handle(MemberWelcomeAcceptedEvent $event): void
    {
        $memberLabel = $event->user->name !== '' ? $event->user->name : $event->user->email;

        $this->logActivity(
            logName: 'panel.member',
            subject: $event->user,
            causer: $event->user,
            event: 'welcome_accepted',
            message: __(':member_label, hoş geldin bağlantısını açtı.', ['member_label' => $memberLabel]),
            properties: [
                'user_id' => $event->user->id,
                'email_verified' => $event->emailVerified,
            ],
        );
    }
}
