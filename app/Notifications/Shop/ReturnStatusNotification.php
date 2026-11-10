<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\ReturnStatusMail;
use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class ReturnStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): ReturnStatusMail
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->email;

        return (new ReturnStatusMail($this->returnRequest))
            ->to($to);
    }
}
