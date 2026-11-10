<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\AccountTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cariye bir hareket yazıldı (ters kayıtlar dahil). ERP modülleri bu olayı
 * dinleyerek hareketi muhasebe programına aktarır.
 */
class AccountTransactionRecordedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AccountTransaction $transaction,
    ) {}
}
