<?php

declare(strict_types=1);

namespace App\Events\Shop;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ürünün stoğu kritik eşiğe (ya da altına) indi. Stok değişiklikleri transaction
 * içinde olduğundan olay commit sonrasına ertelenir; geri alınan işlem bildirim üretmez.
 */
class StockLowEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Product $product,
    ) {}
}
