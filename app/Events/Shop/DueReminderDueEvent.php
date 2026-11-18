<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\Account;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bayinin vadesi geçen / yaklaşan borcu için hatırlatma zamanı geldi
 * (DueReminderService). Listeler CreditService::dues() satırlarıdır.
 */
class DueReminderDueEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $emails
     * @param  array<int, array<string, mixed>>  $overdue
     * @param  array<int, array<string, mixed>>  $upcoming
     */
    public function __construct(
        public readonly Account $account,
        public readonly array $emails,
        public readonly array $overdue,
        public readonly array $upcoming,
    ) {}
}
