<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\UnappliedPaymentReceivedEvent;
use App\Support\Money;

class LogUnappliedPayment
{
    public function handle(UnappliedPaymentReceivedEvent $event): void
    {
        activity('order')
            ->performedOn($event->order)
            ->event('unapplied_payment')
            ->withProperties(['payment_id' => $event->payment->id, 'reason' => $event->reason])
            ->log(__(':number numaralı siparişe uygulanamayan :amount :currency ödeme alındı (:reason).', [
                'number' => $event->order->number,
                'amount' => Money::number($event->payment->amount),
                'currency' => $event->payment->currency_code,
                'reason' => $event->reason,
            ]));
    }
}
