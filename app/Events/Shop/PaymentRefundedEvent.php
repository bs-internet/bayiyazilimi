<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\Payment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Kart ödemesi sağlayıcı üzerinden (tamamen ya da kısmen) iade edildi. */
class PaymentRefundedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly BigDecimal $amount,
        public readonly User $causer,
    ) {}
}
