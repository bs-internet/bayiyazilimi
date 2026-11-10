<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Enums\Shop\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $previous,
        public readonly User $causer,
        public readonly bool $notify = false,
        public readonly ?string $note = null,
    ) {}
}
