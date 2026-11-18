<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\DueReminderDueEvent;
use App\Notifications\Shop\DueReminderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Vade hatırlatması bayinin cari adreslerine e-postayla gider.
 */
class SendDueReminder
{
    public function handle(DueReminderDueEvent $event): void
    {
        Notification::route('mail', $event->emails)
            ->notify(new DueReminderNotification($event->account, $event->overdue, $event->upcoming));
    }
}
