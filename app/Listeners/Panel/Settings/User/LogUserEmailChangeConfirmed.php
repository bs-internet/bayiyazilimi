<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\User;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\User\UserEmailChangeConfirmedEvent;

class LogUserEmailChangeConfirmed
{
    use LogsActivity;

    public function handle(UserEmailChangeConfirmedEvent $event): void
    {
        $userLabel = $event->user->name !== '' ? $event->user->name : $event->newEmail;

        $this->logActivity(
            logName: 'settings.user',
            subject: $event->user,
            causer: $event->user,
            event: 'email_changed',
            message: __(':user_label, e-posta adresini :old_email → :new_email olarak onayladı.', ['user_label' => $userLabel, 'old_email' => $event->oldEmail, 'new_email' => $event->newEmail]),
            properties: [
                'user_id' => $event->user->id,
                'old_email' => $event->oldEmail,
                'new_email' => $event->newEmail,
            ],
        );
    }
}
