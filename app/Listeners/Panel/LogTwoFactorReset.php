<?php

declare(strict_types=1);

namespace App\Listeners\Panel;

use App\Concerns\LogsActivity;
use App\Events\Panel\TwoFactorResetEvent;

class LogTwoFactorReset
{
    use LogsActivity;

    public function handle(TwoFactorResetEvent $event): void
    {
        $userLabel = $event->user->name !== '' ? $event->user->name : $event->user->email;

        $this->logActivity(
            // Panel kullanıcılarının hesabı (account) yoktur; üyelerin vardır.
            logName: $event->user->account_id === null ? 'settings.user' : 'panel.member',
            subject: $event->user,
            causer: $event->causer,
            event: 'two_factor_reset',
            message: __(':causer_name, :user_label kullanıcısının iki adımlı doğrulamasını sıfırladı.', ['causer_name' => $event->causer->name, 'user_label' => $userLabel]),
            properties: ['user_id' => $event->user->id],
        );
    }
}
