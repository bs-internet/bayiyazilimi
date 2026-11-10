<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\ResolvesBasketArea;
use App\Http\Resources\Shop\BasketPresenter;
use App\Models\Currency;
use App\Models\Product;
use App\Services\Shop\Basket;
use App\Services\Shop\CurrencyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Oturum sepetinin ortak kuralları. Alan (sayfa, rota öneki, ek prop'lar) alt
 * sınıfta: bayi paneli App\Http\Controllers\App\Shop\CartController ("Sipariş
 * ver"), vitrin modülünde kendi CartController'ı.
 */
abstract class CartController extends Controller
{
    use ResolvesBasketArea;

    /**
     * Sepet sayfasının Inertia adı.
     */
    abstract protected function page(): string;

    /**
     * Alana özgü ek sayfa verisi.
     *
     * @return array<string, mixed>
     */
    protected function indexProps(Currency $currency): array
    {
        return [];
    }

    public function index(CurrencyManager $currencies): Response
    {
        $cart = Basket::cart();
        $currency = $currencies->current();

        return Inertia::render($this->page(), [
            'lines' => BasketPresenter::lines($cart->lines($currency), $currency),
            'totals' => BasketPresenter::totals($cart->totals($currency), $currency),
            ...$this->indexProps($currency),
        ]);
    }

    /**
     * Sepetteki ürünleri teklif listesine taşır ("Teklif listesine taşı").
     */
    public function moveToQuote(): RedirectResponse
    {
        $moved = Basket::cart()->moveTo(Basket::quote());

        return to_route($this->basketRoute('quote-list.index'))->with('toast', [
            'type' => $moved > 0 ? 'success' : 'info',
            'message' => $moved > 0 ? __(':moved ürün teklif listenize taşındı.', ['moved' => $moved]) : __('Sepetiniz boş.'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['array'],
            'quantities.*' => ['integer', 'min:0', 'max:100000'],
        ]);

        $cart = Basket::cart();
        $products = Product::query()->visible()->whereIn('id', array_keys($data['quantities'] ?? []))->get()->keyBy('id');
        $warnings = [];

        foreach ($data['quantities'] ?? [] as $id => $qty) {
            $product = $products->get($id);

            if ($product === null) {
                $cart->remove((string) $id);

                continue;
            }

            $qty = (int) $qty;

            if ($qty > 0) {
                $qty = max($qty, $product->min_qty);

                if ($product->max_qty) {
                    $qty = min($qty, $product->max_qty);
                }

                if (! $product->hasStockFor($qty)) {
                    $qty = max(1, $product->stock);
                    $warnings[] = __(':name için en fazla :stock adet eklenebilir.', ['name' => $product->name, 'stock' => $product->stock]);
                }
            }

            $cart->set($product, $qty);
        }

        $redirect = to_route($this->basketRoute('cart.index'));

        // Adet kutusundan anlık güncelleme (auto=1): yalnız uyarı varsa bildirim.
        if ($warnings) {
            return $redirect->with('warning', implode(' ', $warnings));
        }

        return $request->boolean('auto') ? $redirect : $redirect->with('success', __('Sepetiniz güncellendi.'));
    }

    public function remove(string $product): RedirectResponse
    {
        Basket::cart()->remove($product);

        return to_route($this->basketRoute('cart.index'))->with('success', __('Ürün sepetten çıkarıldı.'));
    }
}
