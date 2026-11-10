<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\ReturnReceivedMail;
use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class ReturnReceivedNotification extends Notification implements ShouldQueue
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

    public function toMail(object $notifiable): ReturnReceivedMail
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->email;

        return (new ReturnReceivedMail($this->returnRequest))
            ->to($to);
    }
}
