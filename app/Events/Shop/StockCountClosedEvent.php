<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\StockCount;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sayım oturumu onaylandı ya da iptal edildi (`$count->status`).
 */
class StockCountClosedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly StockCount $count,
        public readonly User $causer,
    ) {}
}
