<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Enums\Shop\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Teklif durumu değişti. $byCustomer: müşterinin işlemi (onay, revize, ret,
 * geri çekme); değilse yöneticinin (gönderim, iptal).
 */
class QuoteStatusChangedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly QuoteStatus $previous,
        public readonly ?User $causer = null,
        public readonly bool $byCustomer = false,
        public readonly ?string $note = null,
    ) {}
}
