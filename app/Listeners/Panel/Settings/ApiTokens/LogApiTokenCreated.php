<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\ApiTokens;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\ApiTokens\ApiTokenCreatedEvent;

class LogApiTokenCreated
{
    use LogsActivity;

    public function handle(ApiTokenCreatedEvent $event): void
    {
        $this->logActivity(
            logName: 'panel.api-token',
            causer: $event->causer,
            event: 'created',
            message: __(':causer_name, :token_name adlı API anahtarını oluşturdu.', ['causer_name' => $event->causer->name, 'token_name' => $event->token->name]),
            subject: $event->token,
            properties: [
                'abilities' => $event->token->abilities,
                'expires_at' => $event->token->expires_at?->toIso8601String(),
            ],
        );
    }
}
