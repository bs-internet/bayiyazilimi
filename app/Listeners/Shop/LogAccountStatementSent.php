<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\AccountStatementSentEvent;

class LogAccountStatementSent
{
    use LogsActivity;

    public function handle(AccountStatementSentEvent $event): void
    {
        $this->logActivity(
            logName: 'ledger',
            subject: $event->account,
            causer: $event->causer,
            event: 'ledger_statement_sent',
            message: __(':name, :account carisinin ekstresini e-postayla gönderdi (:emails).', [
                'name' => $event->causer->name,
                'account' => $event->account->title ?? $event->account->code,
                'emails' => implode(', ', $event->emails),
            ]),
        );
    }
}
