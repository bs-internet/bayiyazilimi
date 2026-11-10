<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop\Concerns;

use App\Models\Order;

/**
 * Sipariş, teklif, adres, ödeme ve iade ekranlarının alanı. Çekirdekte bayi
 * paneli (`/app`, app.shop.*); vitrin modülü aynı controller'ları alt sınıfla
 * kendi "Hesabım" alanında (`/hesabim`, shop.account.*) sunar ve bu
 * yöntemleri ezer. Rota adlarının son ekleri iki alanda aynıdır.
 */
trait ResolvesMemberArea
{
    /**
     * Inertia sayfa öneki: `orders/index` → `app/shop/orders/index`.
     */
    protected function pagePrefix(): string
    {
        return 'app/shop/';
    }

    /**
     * Rota adı öneki: `returns.show` → `app.shop.returns.show`.
     */
    protected function areaPrefix(): string
    {
        return 'app.shop.';
    }

    /**
     * Teklif ödemesinin sayfası.
     */
    protected function checkoutPage(): string
    {
        return 'app/shop/checkout/index';
    }

    /**
     * Teklif ödemesi sonrası sipariş adresi.
     */
    protected function placedUrl(Order $order): string
    {
        return route('app.shop.orders.show', $order->id);
    }

    /**
     * Tekrar sipariş sonrası açılan sepet (bayide Sipariş ver sayfası).
     */
    protected function cartRoute(): string
    {
        return 'app.shop.cart.index';
    }

    protected function page(string $name): string
    {
        return $this->pagePrefix().$name;
    }

    protected function areaRoute(string $name): string
    {
        return $this->areaPrefix().$name;
    }
}
