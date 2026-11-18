<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\AccountStatementMail;
use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

class AccountStatementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Account $account,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountStatementMail
    {
        $to = $notifiable instanceof AnonymousNotifiable ? $notifiable->routeNotificationFor('mail') : $notifiable->email;

        return (new AccountStatementMail($this->account, $this->from, $this->to))->to($to);
    }
}
