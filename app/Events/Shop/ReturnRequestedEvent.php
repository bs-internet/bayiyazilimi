<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\ReturnRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnRequestedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
    ) {}
}
