<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Yönetici cari ekstreyi bayiye e-postayla gönderdi (Excel ekli).
 */
class AccountStatementSentEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $emails
     */
    public function __construct(
        public readonly Account $account,
        public readonly array $emails,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly User $causer,
    ) {}
}
