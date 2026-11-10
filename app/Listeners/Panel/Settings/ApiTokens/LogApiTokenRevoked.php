<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\ApiTokens;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\ApiTokens\ApiTokenRevokedEvent;

class LogApiTokenRevoked
{
    use LogsActivity;

    public function handle(ApiTokenRevokedEvent $event): void
    {
        $this->logActivity(
            logName: 'panel.api-token',
            causer: $event->causer,
            event: 'revoked',
            message: __(':causer_name, :token_name adlı API anahtarını iptal etti.', ['causer_name' => $event->causer->name, 'token_name' => $event->tokenName]),
            properties: [
                'token_id' => $event->tokenId,
                'owner' => $event->ownerName,
            ],
        );
    }
}
