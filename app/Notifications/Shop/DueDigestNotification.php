<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\DueDigestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DueDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $overdue,
        public readonly bool $upcoming,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): DueDigestMail
    {
        return (new DueDigestMail($this->rows, $this->overdue, $this->upcoming))->to($notifiable->email);
    }
}
