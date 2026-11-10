<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop\Concerns;

/**
 * Sepet, teklif listesi ve ödeme iş kuralları ortak taban controller'larda
 * (App\Http\Controllers\Shop\*Controller, abstract); hangi alanda sunuldukları
 * alt sınıfta belirlenir. Çekirdekte bayi paneli (`app.shop.*`,
 * App\Http\Controllers\App\Shop\*), vitrin modülünde vitrin (`shop.*`).
 */
trait ResolvesBasketArea
{
    /**
     * Alanın rota adı öneki, ör. `app.shop.`.
     */
    abstract protected function routePrefix(): string;

    /**
     * Rota adı: `cart.index` → `{önek}cart.index`.
     */
    protected function basketRoute(string $name): string
    {
        return $this->routePrefix().$name;
    }
}
