<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Enums\Shop\PaymentNotificationStatus;
use App\Events\Shop\PaymentNotificationReviewedEvent;
use App\Events\Shop\PaymentNotificationSubmittedEvent;
use App\Support\Money;

/**
 * Havale bildirimi etkinlik günlüğü: bayinin bildirimi ve yöneticinin kararı.
 */
class LogPaymentNotification
{
    use LogsActivity;

    public function handleSubmitted(PaymentNotificationSubmittedEvent $event): void
    {
        $n = $event->notification->loadMissing('user');

        if ($n->user === null) {
            return;
        }

        $this->logActivity(
            logName: 'ledger',
            subject: $n,
            causer: $n->user,
            event: 'created',
            message: __(':name, :amount tutarında havale bildirimi yaptı.', ['name' => $n->user->name, 'amount' => Money::format($n->amount)]),
        );
    }

    public function handleReviewed(PaymentNotificationReviewedEvent $event): void
    {
        $n = $event->notification;
        $approved = $n->status === PaymentNotificationStatus::Approved;

        $this->logActivity(
            logName: 'ledger',
            subject: $n,
            causer: $event->causer,
            event: $approved ? 'approved' : 'rejected',
            message: $approved
                ? __(':name, :amount tutarındaki havale bildirimini onayladı.', ['name' => $event->causer->name, 'amount' => Money::format($n->amount)])
                : __(':name, :amount tutarındaki havale bildirimini reddetti: :reason', ['name' => $event->causer->name, 'amount' => Money::format($n->amount), 'reason' => $n->reject_reason]),
        );
    }
}
