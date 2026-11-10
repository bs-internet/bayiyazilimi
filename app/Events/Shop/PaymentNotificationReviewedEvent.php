<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\PaymentNotification;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Ödeme bildirimi onaylandı ya da reddedildi. */
class PaymentNotificationReviewedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PaymentNotification $notification,
        public readonly User $causer,
    ) {}
}
