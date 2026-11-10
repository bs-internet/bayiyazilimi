<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siparişe bağlı stok işlemleri: sipariş düşümü, iptalde geri ekleme, iade
 * girişi. Stok değişiklikleri, net toplama listesi (orderPicks) ve lot
 * değişimi StockService'tedir.
 */
class OrderStockService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * Sipariş için stok düşer. Önce siparişin deposundan (yoksa varsayılan),
     * yetmezse diğer satılabilir depolardan sırayla düşer. Depo içinde lot
     * (FEFO) ve lokasyonlar otomatik seçilir.
     *
     * Stok yetmezse kalan ilk depodan eksiye düşer (ön sipariş, teklif, ödemesi
     * alınmış sipariş). `allowShortage: false` iken ön siparişe kapalı bir ürünün
     * stoğu yetmiyorsa hiçbir şey düşülmez ve ShopException fırlatılır.
     */
    public function deductForOrder(Order $order, bool $allowShortage = true): void
    {
        if ($order->stock_deducted) {
            return;
        }

        DB::transaction(function () use ($order, $allowShortage) {
            $order->loadMissing('items.product');
            $warehouses = $this->sellableWarehouses($order->warehouse_id);

            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product === null || ! $product->track_stock) {
                    continue;
                }

                $remaining = $item->quantity;

                if (! $allowShortage && ! $product->allow_backorder) {
                    ProductStock::query()->lockForUpdate()->where('product_id', $product->id)->get();
                    $available = $warehouses->sum(fn (Warehouse $w) => $this->stock->sellableIn($product, $w));

                    if ($available < $remaining) {
                        throw new ShopException(__(':name için yeterli stok yok (mevcut: :available, gereken: :remaining).', ['name' => $product->name, 'available' => $available, 'remaining' => $remaining]));
                    }
                }

                foreach ($warehouses as $warehouse) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($this->stock->sellableIn($product, $warehouse), $remaining);

                    if ($take > 0) {
                        $this->stock->change($product, $warehouse, -$take, StockMovementType::Sale, __('Sipariş :number', ['number' => $order->number]), null, $order, false);
                        $remaining -= $take;
                    }
                }

                // Stok yetmediyse (ön sipariş) kalan ilk depodan eksiye düşer.
                if ($remaining > 0 && $warehouses->isNotEmpty()) {
                    $this->stock->change($product, $warehouses->first(), -$remaining, StockMovementType::Sale, __('Sipariş :number', ['number' => $order->number]), null, $order, false);
                }

                $product->increment('sales_count', $item->quantity);
            }

            $order->forceFill(['stock_deducted' => true])->save();
        });
    }

    /**
     * İptal: siparişin üzerinde kalan (net) düşümleri aynı depo, lokasyon ve
     * lotlara geri ekler. Net hesap sayesinde iptal → yeniden açma → iptal
     * döngüsünde stok iki kez geri eklenmez.
     */
    public function restoreForOrder(Order $order, ?User $user = null): void
    {
        if (! $order->stock_deducted) {
            return;
        }

        DB::transaction(function () use ($order, $user) {
            foreach ($this->stock->orderPicks($order) as $pick) {
                if ($pick['product'] === null || $pick['warehouse'] === null || $pick['quantity'] <= 0) {
                    continue;
                }

                $this->stock->change(
                    $pick['product'],
                    $pick['warehouse'],
                    $pick['quantity'],
                    StockMovementType::Return,
                    __('Sipariş :number iptal/iade', ['number' => $order->number]),
                    $user,
                    $order,
                    false,
                    $pick['location'],
                    $pick['lot'],
                );
            }

            $order->forceFill(['stock_deducted' => false])->save();
        });
    }

    /**
     * İade edilen ürünü siparişte düştüğü depo, lokasyon ve lotlara (düşüm
     * sırasıyla) geri ekler. Siparişte düşüm yoksa ya da miktarı aşarsa kalan
     * kısım `fallback` depoya otomatik lokasyonla girer.
     */
    public function restockFromOrder(
        Order $order,
        Product $product,
        int $quantity,
        Warehouse $fallback,
        ?string $note = null,
        ?User $user = null,
        ?Model $reference = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $product, $quantity, $fallback, $note, $user, $reference) {
            $remaining = $quantity;

            foreach ($this->stock->orderPicks($order)->where('product_id', $product->id) as $pick) {
                if ($remaining <= 0) {
                    break;
                }

                if ($pick['warehouse'] === null || $pick['quantity'] <= 0) {
                    continue;
                }

                $part = min($pick['quantity'], $remaining);
                $this->stock->change($product, $pick['warehouse'], $part, StockMovementType::Return, $note, $user, $reference, false, $pick['location'], $pick['lot']);
                $remaining -= $part;
            }

            if ($remaining > 0) {
                $this->stock->change($product, $fallback, $remaining, StockMovementType::Return, $note, $user, $reference, false);
            }
        });
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function sellableWarehouses(?string $preferredId)
    {
        $preferredId ??= Warehouse::default()?->id;

        return Warehouse::query()
            ->where('status', Status::Active->value)
            ->where('is_sellable', true)
            ->get()
            ->sortBy(fn (Warehouse $w) => [$w->id === $preferredId ? 0 : 1, $w->sort_order])
            ->values();
    }
}
