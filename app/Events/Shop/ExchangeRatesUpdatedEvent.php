<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExchangeRatesUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $rates,
        public readonly string $source,
        public readonly ?User $causer = null,
    ) {}
}
