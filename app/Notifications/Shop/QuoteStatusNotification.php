<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\QuoteStatusMail;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/** Müşteriye: teklifiniz hazır / iptal edildi ya da kendi işleminizin teyidi. */
class QuoteStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Quote $quote,
        public readonly ?string $note = null,
        public readonly bool $byCustomer = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): QuoteStatusMail
    {
        $to = $notifiable instanceof AnonymousNotifiable
            ? $notifiable->routeNotificationFor('mail')
            : $notifiable->email;

        return (new QuoteStatusMail($this->quote, $this->note, $this->byCustomer))
            ->to($to);
    }
}
