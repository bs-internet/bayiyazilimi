<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Enums\Shop\StockCountStatus;
use App\Events\Shop\StockCountClosedEvent;

class LogStockCountClosed
{
    use LogsActivity;

    public function handle(StockCountClosedEvent $event): void
    {
        $c = $event->count->loadMissing('warehouse')->loadCount(['lines', 'movements']);
        $approved = $c->status === StockCountStatus::Completed;

        $this->logActivity(
            logName: 'stock',
            subject: $c,
            causer: $event->causer,
            event: $approved ? 'approved' : 'cancelled',
            message: $approved
                ? __(':name, :name2 deposundaki :number sayımını onayladı (:lines_count satır, :movements_count düzeltme hareketi).', ['name' => $event->causer->name, 'name2' => $c->warehouse?->name, 'number' => $c->number, 'lines_count' => $c->lines_count, 'movements_count' => $c->movements_count])
                : __(':name, :name2 deposundaki :number sayımını iptal etti.', ['name' => $event->causer->name, 'name2' => $c->warehouse?->name, 'number' => $c->number]),
        );
    }
}
