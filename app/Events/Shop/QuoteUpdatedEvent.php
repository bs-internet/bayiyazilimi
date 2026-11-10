<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly User $causer,
    ) {}
}
