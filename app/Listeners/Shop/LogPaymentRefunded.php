<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\PaymentRefundedEvent;
use App\Support\Money;

class LogPaymentRefunded
{
    use LogsActivity;

    public function handle(PaymentRefundedEvent $event): void
    {
        $p = $event->payment;

        $this->logActivity(
            logName: 'ledger',
            subject: $p,
            causer: $event->causer,
            event: 'refunded',
            message: __(':name, :reference referanslı ödemeden :amount :currency iade etti (:method).', ['name' => $event->causer->name, 'reference' => $p->merchant_oid, 'amount' => Money::number($event->amount), 'currency' => $p->currency_code, 'method' => $p->methodLabel()]),
        );
    }
}
