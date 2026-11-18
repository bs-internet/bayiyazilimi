<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\DueReminderMail;
use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class DueReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $overdue
     * @param  array<int, array<string, mixed>>  $upcoming
     */
    public function __construct(
        public readonly Account $account,
        public readonly array $overdue,
        public readonly array $upcoming,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): DueReminderMail
    {
        $to = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor('mail') : $notifiable->email;

        return (new DueReminderMail($this->account, $this->overdue, $this->upcoming))->to($to);
    }
}
