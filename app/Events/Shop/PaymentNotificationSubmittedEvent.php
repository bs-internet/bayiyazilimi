<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\PaymentNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentNotificationSubmittedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PaymentNotification $notification,
    ) {}
}
