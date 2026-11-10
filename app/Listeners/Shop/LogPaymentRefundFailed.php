<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\PaymentRefundFailedEvent;
use App\Models\Payment;
use App\Support\Money;

class LogPaymentRefundFailed
{
    use LogsActivity;

    public function handle(PaymentRefundFailedEvent $event): void
    {
        $refund = $event->refund;
        $payment = Payment::query()->withoutGlobalScopes()->find($refund->payment_id);

        $this->logActivity(
            logName: 'ledger',
            subject: $payment,
            causer: $event->causer,
            event: 'refund_failed',
            message: __(':reference referanslı ödemenin :amount :currency tutarındaki iadesi yapılamadı: :reason', [
                'reference' => $payment?->merchant_oid,
                'amount' => Money::number($refund->amount),
                'currency' => $refund->currency_code,
                'reason' => (string) $refund->failed_reason,
            ]),
            properties: ['refund_id' => $refund->id],
        );
    }
}
