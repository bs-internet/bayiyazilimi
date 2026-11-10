<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Tools\Definitions\Currency;

use App\Concerns\LogsActivity;
use App\Events\Panel\Tools\Definitions\Currency\CurrencyCreatedEvent;

class LogCurrencyCreated
{
    use LogsActivity;

    public function handle(CurrencyCreatedEvent $event): void
    {
        $userName = $event->causer->name;
        $code = $event->currency->code;

        $this->logActivity(
            logName: 'currency',
            subject: $event->currency,
            causer: $event->causer,
            event: 'created',
            message: __(':user_name, :code para birimini oluşturdu.', ['user_name' => $userName, 'code' => $code]),
        );
    }
}
