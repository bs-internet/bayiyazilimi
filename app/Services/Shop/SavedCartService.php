<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Events\Shop\SavedCartChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Bayi kayıtlı sepetleri: oturum sepetinden kaydetme, sepete geri yükleme
 * (değiştir / birleştir), yeniden adlandırma ve silme. Sepete yüklerken
 * vitrinin sepet kuralları (satış biçimi, stok, en az/en çok adet)
 * QuickOrderService üzerinden uygulanır.
 */
class SavedCartService
{
    public function __construct(
        private readonly QuickOrderService $quick,
        private readonly PriceCalculator $prices,
    ) {}

    public function saveFromBasket(User $user, Basket $cart, string $name, ?string $note = null): SavedCart
    {
        $items = collect($cart->raw())
            ->map(fn (int $qty, string $id) => ['product_id' => $id, 'quantity' => $qty])
            ->values()
            ->all();

        if ($items === []) {
            throw new ShopException(__('Sepetiniz boş; kaydedilecek ürün yok.'));
        }

        $saved = new SavedCart(['name' => $name, 'note' => $note, 'items' => $items]);
        $saved->user_id = $user->id;
        $saved->account_id = $user->account_id;
        $saved->save();

        event(new SavedCartChangedEvent($saved, 'created', $user));

        return $saved;
    }

    public function update(SavedCart $saved, User $user, string $name, ?string $note): void
    {
        $saved->update(['name' => $name, 'note' => $note]);

        event(new SavedCartChangedEvent($saved, 'updated', $user));
    }

    /**
     * Kayıtlı sepetin adetlerini günceller ("Sepeti güncelle"); 0 olan ürün çıkar.
     *
     * @param  array<string, int|string>  $quantities  ürün id => adet
     */
    public function updateQuantities(SavedCart $saved, User $user, array $quantities): void
    {
        $items = collect($saved->quantities())
            ->map(fn (int $qty, string $id) => array_key_exists($id, $quantities) ? (int) $quantities[$id] : $qty)
            ->filter(fn (int $qty) => $qty > 0)
            ->map(fn (int $qty, string $id) => ['product_id' => $id, 'quantity' => $qty])
            ->values()
            ->all();

        if ($items === []) {
            throw new ShopException(__('Kayıtlı sepette en az bir ürün kalmalı; isterseniz sepeti silebilirsiniz.'));
        }

        $saved->update(['items' => $items]);

        event(new SavedCartChangedEvent($saved, 'updated', $user));
    }

    public function delete(SavedCart $saved, User $user): void
    {
        $saved->delete();

        event(new SavedCartChangedEvent($saved, 'deleted', $user));
    }

    /**
     * Kayıtlı sepeti oturum sepetine yükler. `replace` → mevcut sepet boşaltılır,
     * `merge` → adetler mevcut sepete eklenir.
     *
     * @return array{added: array<int, array{sku: string, name: string, quantity: int}>, failed: array<int, array{sku: string, reason: string}>}
     */
    public function loadIntoBasket(SavedCart $saved, Basket $cart, string $mode, User $user): array
    {
        if ($mode === 'replace') {
            $cart->clear();
        }

        $quantities = $saved->quantities();
        $products = Product::query()->visible()->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        $result = ['added' => [], 'failed' => []];

        foreach ($quantities as $id => $qty) {
            $product = $products->get($id);

            if ($product === null) {
                $result['failed'][] = ['sku' => '—', 'reason' => __('Ürün artık satışta değil.')];

                continue;
            }

            $error = $this->quick->add($product, $qty, $cart);

            $error === null
                ? $result['added'][] = ['sku' => $product->sku, 'name' => $product->name, 'quantity' => $qty]
                : $result['failed'][] = ['sku' => $product->sku, 'reason' => $error];
        }

        event(new SavedCartChangedEvent($saved, 'loaded', $user));

        return $result;
    }

    /**
     * Kalemler güncel fiyat ve stok bilgisiyle (detay sayfası).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(SavedCart $saved, Currency $currency): Collection
    {
        $quantities = $saved->quantities();

        if ($quantities === []) {
            return collect();
        }

        $products = Product::query()
            ->visible()
            ->with(['currency', 'images', 'brand:id,name'])
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        return collect($quantities)->map(function (int $qty, string $id) use ($products, $currency) {
            /** @var Product|null $product */
            $product = $products->get($id);

            if ($product === null) {
                return ['id' => $id, 'available' => false, 'quantity' => $qty];
            }

            $hasPrice = $product->price->isPositive();
            $unitGross = $hasPrice ? $this->prices->gross($product, $currency) : Money::round(0);
            $lineGross = Money::times($unitGross, $qty);

            $warning = match (true) {
                ! $product->isPurchasable() => __('Yalnızca teklif ile satılıyor.'),
                ! $product->hasStockFor($qty) => __('Yeterli stok yok (mevcut: :stock).', ['stock' => $product->stock]),
                $product->max_qty && $qty > $product->max_qty => __('En fazla :max_qty adet sipariş verilebilir.', ['max_qty' => $product->max_qty]),
                $qty < $product->min_qty => __('En az :min_qty adet sipariş verilebilir.', ['min_qty' => $product->min_qty]),
                default => null,
            };

            return [
                'id' => $id,
                'available' => true,
                'slug' => $product->slug,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand?->name,
                'image' => $product->coverUrl(),
                'quantity' => $qty,
                'has_price' => $hasPrice,
                'unit_gross' => $unitGross,
                'unit_gross_formatted' => $hasPrice ? $currency->format($unitGross) : null,
                'line_gross' => $lineGross,
                'line_gross_formatted' => $hasPrice ? $currency->format($lineGross) : null,
                'stock' => $product->stock,
                'warning' => $warning,
            ];
        })->values();
    }

    /**
     * Liste için ürün sayısı ve güncel fiyatlarla tahmini tutar (KDV dahil, kampanyasız).
     * Tüm sepetlerin ürünleri tek sorguda okunur.
     *
     * @param  Collection<int, SavedCart>  $carts
     * @return array<string, array{count: int, total: BigDecimal}>
     */
    public function estimates(Collection $carts, Currency $currency): array
    {
        $ids = $carts->flatMap(fn (SavedCart $c) => array_keys($c->quantities()))->unique()->values()->all();
        $products = $ids === []
            ? collect()
            : Product::query()->visible()->with('currency')->whereIn('id', $ids)->get()->keyBy('id');

        $result = [];

        foreach ($carts as $cart) {
            $lines = [];

            foreach ($cart->quantities() as $id => $qty) {
                $product = $products->get($id);

                if ($product !== null && $product->price->isPositive()) {
                    $lines[] = Money::times($this->prices->gross($product, $currency), $qty);
                }
            }

            $result[$cart->id] = ['count' => $cart->itemCount(), 'total' => Money::add(...$lines)];
        }

        return $result;
    }
}
