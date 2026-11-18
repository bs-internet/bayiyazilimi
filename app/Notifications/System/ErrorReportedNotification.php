<?php

declare(strict_types=1);

namespace App\Notifications\System;

use App\Events\System\ErrorReportedEvent;
use App\Mail\System\ErrorReportedMail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/**
 * Hata bildirimi. Bilinçli olarak kuyruğa alınmaz (ShouldQueue yok): bildirilen
 * hata kuyruğun ya da zamanlayıcının kendisi olabilir; kuyruktaki bildirim o
 * durumda hiç gitmezdi. Gönderim ErrorAlerts ile seyreltilir.
 */
class ErrorReportedNotification extends Notification
{
    public function __construct(public readonly ErrorReportedEvent $error) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): ErrorReportedMail
    {
        $to = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor('mail') : $notifiable->email;

        return (new ErrorReportedMail($this->error))->to($to);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'system.error',
            'title' => __('Sistemde hata oluştu'),
            'message' => __(':type (:location): :message', [
                'type' => class_basename($this->error->type),
                'location' => $this->error->location,
                'message' => mb_substr($this->error->message, 0, 200),
            ]),
        ];
    }
}
