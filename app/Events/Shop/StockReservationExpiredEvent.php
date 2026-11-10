<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Enums\Shop\OrderStatus;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Stok rezervasyon süresi doldu: ödemesi gelmeyen sipariş "Ödeme Başarısız",
 * onaylanmayan sipariş "İptal Edildi" oldu ve stok serbest bırakıldı (sistem işlemi).
 */
class StockReservationExpiredEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $previous,
        public readonly int $days,
    ) {}
}
