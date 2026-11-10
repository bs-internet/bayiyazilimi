<?php

declare(strict_types=1);

namespace App\Events\Panel\Settings\ApiTokens;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiTokenCreatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PersonalAccessToken $token,
        public readonly User $causer,
    ) {}
}
