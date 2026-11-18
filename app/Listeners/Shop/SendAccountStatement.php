<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\AccountStatementSentEvent;
use App\Notifications\Shop\AccountStatementNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Cari ekstre bayinin cari adreslerine (Account::ledgerEmails) Excel ekiyle gider.
 */
class SendAccountStatement
{
    public function handle(AccountStatementSentEvent $event): void
    {
        Notification::route('mail', $event->emails)
            ->notify(new AccountStatementNotification($event->account, $event->from, $event->to));
    }
}
