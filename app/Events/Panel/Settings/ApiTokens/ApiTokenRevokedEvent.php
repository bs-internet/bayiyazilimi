<?php

declare(strict_types=1);

namespace App\Events\Panel\Settings\ApiTokens;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Anahtar silindikten sonra yayılır; bu yüzden model değil, kaydın bilgileri taşınır.
 */
class ApiTokenRevokedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tokenId,
        public readonly string $tokenName,
        public readonly ?string $ownerName,
        public readonly User $causer,
    ) {}
}
