<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Order;
use App\Models\Product;

/**
 * Üye alanından hızlı sipariş: tek ürün ekleme, SKU listesiyle toplu ekleme ve
 * önceki siparişi tekrarlama. Hepsi vitrinle ortak oturum sepetine (Basket::cart) ekler;
 * vitrinin sepet kurallarını (satış biçimi, stok, en az/en çok adet) uygular.
 */
class QuickOrderService
{
    /**
     * Ürünü sepete (verilmezse asıl sepete) eklemeyi dener; sorun varsa nedenini döner (null = eklendi).
     */
    public function add(Product $product, int $quantity, ?Basket $cart = null): ?string
    {
        $cart ??= Basket::cart();
        $quantity = max($quantity, $product->min_qty, 1);
        $newTotal = $cart->quantityOf($product) + $quantity;

        if (! $product->isActive()) {
            return __('Ürün satışta değil.');
        }

        if (! $product->sale_mode->allowsCart() || ! $product->price->isPositive()) {
            return __('Yalnızca teklif ile satılıyor.');
        }

        if (! $product->hasStockFor($newTotal)) {
            return __('Yeterli stok yok (mevcut: :stock).', ['stock' => $product->stock]);
        }

        if ($product->max_qty && $newTotal > $product->max_qty) {
            return __('En fazla :max_qty adet sipariş verilebilir.', ['max_qty' => $product->max_qty]);
        }

        $cart->add($product, $quantity);

        return null;
    }

    /**
     * Önceki siparişin kalemlerini sepete ekler.
     *
     * @return array{added: array<int, array{sku: string, name: string, quantity: int}>, failed: array<int, array{sku: string, reason: string}>}
     */
    public function reorder(Order $order): array
    {
        $result = ['added' => [], 'failed' => []];
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;

            if ($product === null || $product->trashed()) {
                $result['failed'][] = ['sku' => (string) $item->sku, 'reason' => __('Ürün artık satışta değil.')];

                continue;
            }

            $error = $this->add($product, $item->quantity);

            $error === null
                ? $result['added'][] = ['sku' => $product->sku, 'name' => $product->name, 'quantity' => $item->quantity]
                : $result['failed'][] = ['sku' => $product->sku, 'reason' => $error];
        }

        return $result;
    }
}
