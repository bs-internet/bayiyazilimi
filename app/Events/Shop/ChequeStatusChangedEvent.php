<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Enums\Shop\ChequeStatus;
use App\Models\Cheque;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChequeStatusChangedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cheque $cheque,
        public readonly ChequeStatus $previous,
        public readonly User $causer,
        public readonly ?string $note = null,
    ) {}
}
