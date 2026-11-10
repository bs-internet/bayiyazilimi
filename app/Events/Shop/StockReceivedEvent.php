<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockReceivedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly StockReceipt $receipt,
        public readonly User $causer,
    ) {}
}
