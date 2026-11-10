<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Enums\Shop\ReturnStatus;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnStatusChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ReturnRequest $returnRequest,
        public readonly ReturnStatus $previous,
        public readonly User $causer,
    ) {}
}
