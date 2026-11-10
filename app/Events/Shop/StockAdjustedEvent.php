<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAdjustedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly StockMovement $movement,
        public readonly User $causer,
    ) {}
}
