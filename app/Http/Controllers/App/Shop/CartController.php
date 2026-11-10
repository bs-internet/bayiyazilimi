<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Controllers\Shop\CartController as BaseCartController;
use App\Models\Currency;
use App\Models\SavedCart;
use App\Services\Shop\Basket;

/**
 * Bayi paneli "Sipariş ver" (`/app/siparis-ver`): üstte ürün arama (ad / kod +
 * kategori / marka), altta sepet; bayi sipariş ya da teklif verir.
 */
class CartController extends BaseCartController
{
    use BuildsShopOptions;

    protected function routePrefix(): string
    {
        return 'app.shop.';
    }

    protected function page(): string
    {
        return 'app/shop/cart/index';
    }

    protected function indexProps(Currency $currency): array
    {
        return [
            'quoteCount' => Basket::quote()->count(),
            'savedCartCount' => SavedCart::query()->count(),
            'categories' => $this->categoryOptions(),
            'brands' => $this->brandOptions(),
            'frequentProducts' => QuickOrderController::frequent($currency),
            // "Mağazaya git": vitrin modülü kuruluysa (ve bakımda değilse) vitrin adresi.
            'shopUrl' => hooks()->apply('shop.storefront_url', null),
        ];
    }
}
