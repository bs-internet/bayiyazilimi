<?php

declare(strict_types=1);

namespace App\Notifications\Shop;

use App\Mail\Shop\LowStockMail;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): LowStockMail
    {
        return (new LowStockMail($this->product))->to($notifiable->email);
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(object $notifiable): array
    {
        $stock = $this->product->stock;

        return [
            'type' => 'shop.low_stock',
            'product_id' => $this->product->id,
            'title' => $stock <= 0
                ? __(':sku stoğu tükendi.', ['sku' => $this->product->sku])
                : __(':sku kritik stok seviyesinde.', ['sku' => $this->product->sku]),
            'message' => __(':name — stok: :stock (eşik: :threshold).', [
                'name' => $this->product->name,
                'stock' => $stock,
                'threshold' => $this->product->lowStockThreshold(),
            ]),
        ];
    }
}
