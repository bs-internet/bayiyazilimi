<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\QuoteStatusChangedEvent;

class LogQuoteStatusChanged
{
    use LogsActivity;

    public function handle(QuoteStatusChangedEvent $event): void
    {
        $quote = $event->quote;
        $message = __(':number numaralı teklif \':from\' → \':to\'', ['number' => $quote->number, 'from' => $event->previous->label(), 'to' => $quote->status->label()]);

        if ($event->byCustomer || $event->causer === null) {
            $log = activity('quote')->performedOn($quote)->event('status');

            if ($event->causer !== null) {
                $log->causedBy($event->causer);
            }

            $log->log(__(':message (müşteri).', ['message' => $message]));

            return;
        }

        $this->logActivity(
            logName: 'quote',
            subject: $quote,
            causer: $event->causer,
            event: 'status',
            message: __(':name: :message.', ['name' => $event->causer->name, 'message' => $message]),
        );
    }
}
