<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\StockAdjustedEvent;

class LogStockAdjusted
{
    use LogsActivity;

    public function handle(StockAdjustedEvent $event): void
    {
        $m = $event->movement->loadMissing(['product', 'warehouse']);
        $sign = $m->quantity > 0 ? '+' : '';

        $this->logActivity(
            logName: 'stock',
            subject: $m->product,
            causer: $event->causer,
            event: $m->type->value,
            message: __(':name, :sku stoğunu :name2 deposunda :sign:quantity değiştirdi (:m). Yeni bakiye: :balance_after.', ['name' => $event->causer->name, 'sku' => $m->product?->sku, 'name2' => $m->warehouse?->name, 'sign' => $sign, 'quantity' => $m->quantity, 'm' => $m->type->label(), 'balance_after' => $m->balance_after]),
        );
    }
}
