<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\QuoteRequestedEvent;

class LogQuoteRequested
{
    public function handle(QuoteRequestedEvent $event): void
    {
        $quote = $event->quote;

        activity('quote')
            ->performedOn($quote)
            ->event('created')
            ->log(__(':customer_name, :number numaralı teklif talebini oluşturdu.', ['customer_name' => $quote->customer_name, 'number' => $quote->number]));
    }
}
