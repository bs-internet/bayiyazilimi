<?php

declare(strict_types=1);

namespace App\Listeners\App\Profile;

use App\Concerns\LogsActivity;
use App\Events\App\Profile\ProfileUpdatedEvent;

class LogProfileUpdated
{
    use LogsActivity;

    public function handle(ProfileUpdatedEvent $event): void
    {
        $userName = $event->updatedBy->name;
        $message = $event->emailChanged
            ? __(':user_name, e-posta adresini güncelledi.', ['user_name' => $userName])
            : __(':user_name, bilgilerini güncelledi.', ['user_name' => $userName]);

        $this->logActivity(
            logName: 'profile',
            subject: $event->updatedBy,
            causer: $event->updatedBy,
            event: $event->emailChanged ? 'email_updated' : 'updated',
            message: $message,
        );
    }
}
