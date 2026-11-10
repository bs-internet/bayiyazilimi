<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Tools\Definitions\Currency;

use App\Concerns\LogsActivity;
use App\Events\Panel\Tools\Definitions\Currency\CurrencyRestoredEvent;

class LogCurrencyRestored
{
    use LogsActivity;

    public function handle(CurrencyRestoredEvent $event): void
    {
        $userName = $event->causer->name;
        $code = $event->currency->code;

        $this->logActivity(
            logName: 'currency',
            subject: $event->currency,
            causer: $event->causer,
            event: 'restored',
            message: __(':user_name, :code para birimini geri aldı.', ['user_name' => $userName, 'code' => $code]),
        );
    }
}
