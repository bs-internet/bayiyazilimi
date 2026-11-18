<?php

declare(strict_types=1);

namespace App\Events\Shop;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Yöneticiye giden günlük vade özeti hazır (DueReminderService): bayi bazında
 * vadesi geçen / yaklaşan borçlar.
 */
class DueDigestReadyEvent
{
    use Dispatchable;

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $overdue,
        public readonly bool $upcoming,
    ) {}
}
