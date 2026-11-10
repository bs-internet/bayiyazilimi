<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\ExchangeRatesUpdatedEvent;

class LogExchangeRatesUpdated
{
    use LogsActivity;

    public function handle(ExchangeRatesUpdatedEvent $event): void
    {
        $summary = collect($event->rates)->map(fn ($rate, $code) => __(':code: :rate', ['code' => $code, 'rate' => $rate]))->implode(', ');
        $source = $event->source === 'tcmb' ? 'TCMB' : __('elle');
        $message = $summary === '' ? __('Döviz kurları kontrol edildi (:source), değişiklik yok.', ['source' => $source]) : __('Döviz kurları güncellendi (:source): :summary', ['source' => $source, 'summary' => $summary]);

        if ($event->causer === null) {
            activity('currency')->event('rates')->withProperties(['rates' => $event->rates])->log($message);

            return;
        }

        $this->logActivity(
            logName: 'currency',
            causer: $event->causer,
            event: 'rates',
            message: __(':name — :message', ['name' => $event->causer->name, 'message' => $message]),
            properties: ['rates' => $event->rates],
        );
    }
}
