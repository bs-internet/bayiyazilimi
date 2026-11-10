<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

class LogUserVerified
{
    use LogsActivity;

    public function handle(Verified $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $userLabel = $user->name !== '' ? $user->name : $user->email;

        $this->logActivity(
            logName: 'auth',
            subject: $user,
            causer: $user,
            event: 'verified',
            message: __(':user_label, e-posta adresini doğruladı.', ['user_label' => $userLabel]),
        );
    }
}
