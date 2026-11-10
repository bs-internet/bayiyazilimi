<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\QuoteUpdatedEvent;

class LogQuoteUpdated
{
    use LogsActivity;

    public function handle(QuoteUpdatedEvent $event): void
    {
        $this->logActivity(
            logName: 'quote',
            subject: $event->quote,
            causer: $event->causer,
            event: 'updated',
            message: __(':name, :number numaralı teklifi güncelledi.', ['name' => $event->causer->name, 'number' => $event->quote->number]),
        );
    }
}
