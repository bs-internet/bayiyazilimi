<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\QuoteReceivedMail;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class QuoteReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Quote $quote,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): QuoteReceivedMail
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->email;

        return (new QuoteReceivedMail($this->quote))
            ->to($to);
    }
}
