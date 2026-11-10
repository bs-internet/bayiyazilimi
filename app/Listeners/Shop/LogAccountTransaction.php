<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\AccountTransactionRecordedEvent;
use App\Support\Money;

/**
 * Yöneticinin elle yazdığı cari hareketler (tahsilat, dekont, açılış, iptal)
 * etkinlik günlüğüne düşer; sistemin otomatik hareketleri belgesinin
 * (sipariş / ödeme / iade) kendi günlüğündedir.
 */
class LogAccountTransaction
{
    use LogsActivity;

    public function handle(AccountTransactionRecordedEvent $event): void
    {
        $t = $event->transaction;
        $causer = $t->user;

        if ($causer === null) {
            return;
        }

        $account = $t->account()->withoutGlobalScopes()->first();
        $what = $t->isReversal() ? __('iptal kaydı') : $t->type->label().' ('.$t->direction->label().')';

        $this->logActivity(
            logName: 'ledger',
            subject: $account,
            causer: $causer,
            event: $t->isReversal() ? 'ledger_reversed' : 'ledger_recorded',
            message: __(':causer, :account carisine :what yazdı: :amount.', ['causer' => $causer->name, 'account' => $account?->title ?? __('müşteri'), 'what' => $what, 'amount' => Money::format($t->amount)]),
            properties: ['transaction_id' => $t->id, 'description' => $t->description],
        );
    }
}
