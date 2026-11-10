<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\StockReceivedEvent;

class LogStockReceived
{
    use LogsActivity;

    public function handle(StockReceivedEvent $event): void
    {
        $r = $event->receipt->loadMissing(['supplier', 'warehouse'])->loadCount('movements');

        $this->logActivity(
            logName: 'stock',
            subject: $r,
            causer: $event->causer,
            event: 'received',
            message: __(':name, :name2 tedarikçisinin :document_no numaralı :r belgesiyle :name3 deposuna mal kabul yaptı (:number, :movements_count hareket).', ['name' => $event->causer->name, 'name2' => $r->supplier?->name, 'document_no' => $r->document_no, 'r' => $r->document_type->label(), 'name3' => $r->warehouse?->name, 'number' => $r->number, 'movements_count' => $r->movements_count]),
        );
    }
}
