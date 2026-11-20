<?php

declare(strict_types=1);

namespace App\Notifications\System;

use App\Mail\System\DatabaseBackupMail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/**
 * Günlük yedek e-postası. Kuyruğa alınmaz: ek geçici bir dosyadır, gönderimden
 * hemen sonra silinir.
 */
class DatabaseBackupNotification extends Notification
{
    public function __construct(
        public readonly string $fileName,
        public readonly int $size,
        public readonly ?string $attachment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): DatabaseBackupMail
    {
        $to = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor('mail') : $notifiable->email;

        return (new DatabaseBackupMail($this->fileName, $this->size, $this->attachment))->to($to);
    }
}
