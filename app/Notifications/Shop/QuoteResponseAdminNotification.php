<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\QuoteResponseAdminMail;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/** Mağazaya: müşteri teklifi onayladı / revize istedi / reddetti / geri çekti. */
class QuoteResponseAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Quote $quote,
        public readonly ?string $note = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): QuoteResponseAdminMail
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->email;

        return (new QuoteResponseAdminMail($this->quote, $this->note))
            ->to($to);
    }
}
