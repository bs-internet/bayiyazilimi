<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Http\Controllers\Shop\CheckoutController as BaseCheckoutController;
use App\Models\Order;

/**
 * Bayi paneli ödeme (`/app/odeme`); sipariş sonrası bayi panelindeki sipariş detayına dönülür.
 */
class CheckoutController extends BaseCheckoutController
{
    protected function routePrefix(): string
    {
        return 'app.shop.';
    }

    protected function page(): string
    {
        return 'app/shop/checkout/index';
    }

    protected function placedUrl(Order $order): string
    {
        return route('app.shop.orders.show', $order->id);
    }
}
