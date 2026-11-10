<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\QuoteStatus;
use App\Http\Controllers\App\Shop\OrderController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Quote;
use App\Services\Shop\Basket;
use App\Services\Shop\CurrencyManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Üye başlangıç ekranı: e-ticaret özeti, son siparişler (tekrar sipariş),
 * ve teklifler (sipariş vermek: Sipariş ver sayfası). Order/Quote sorguları hesap kapsamlıdır (bind_account).
 */
class DashboardController extends Controller
{
    public function index(Request $request, CurrencyManager $currencies): Response
    {
        $currency = $currencies->current();
        $cart = Basket::cart();
        $cartTotals = $cart->isEmpty() ? null : $cart->totals($currency);

        $openStatuses = [OrderStatus::Pending->value, OrderStatus::AwaitingApproval->value, OrderStatus::Processing->value, OrderStatus::Shipped->value];

        $recentOrders = Order::query()->withCount('items')->latest()->limit(5)->get();
        $lastOrder = $recentOrders->first();

        $recentQuotes = Quote::query()->visibleToCustomer()->latest()->limit(5)->get();

        $user = $request->user();
        $user->loadMissing('account.dealerGroup');
        $account = $user->account;

        return Inertia::render('app/dashboard', [
            'stats' => [
                'open_orders' => Order::query()->whereIn('status', $openStatuses)->count(),
                'total_orders' => Order::query()->count(),
                'pending_quotes' => Quote::query()->where('status', QuoteStatus::Sent->value)->notExpired()->count(),
                'approved_quotes' => Quote::query()->where('status', QuoteStatus::Approved->value)->whereNull('order_id')->notExpired()->count(),
                'cart_count' => $cart->count(),
                'cart_total' => $cartTotals !== null ? $currency->format($cartTotals['total']) : null,
                'last_order' => $lastOrder ? [
                    'id' => $lastOrder->id,
                    'number' => $lastOrder->number,
                    'status' => $lastOrder->status->value,
                    'status_label' => $lastOrder->status->label(),
                ] : null,
            ],
            'orderUrl' => route('app.shop.cart.index'),
            'recentOrders' => $recentOrders->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'items_count' => $o->items_count,
                'grand_total' => OrderController::money($o->grand_total, $o->currency_code),
                'created_at' => $o->created_at?->toIso8601String(),
            ])->values(),
            'recentQuotes' => $recentQuotes->map(fn (Quote $q) => [
                'id' => $q->id,
                'number' => $q->number,
                'status' => $q->status->value,
                'status_label' => $q->customerStatusLabel(),
                'grand_total' => $q->status->isPriced() ? OrderController::money($q->grand_total, $q->currency_code) : null,
                'valid_until' => $q->valid_until?->toDateString(),
                'order_url' => $q->canBeOrdered() ? route('app.shop.quotes.checkout', $q->id) : null,
            ])->values(),
            // Bayi hesabı değilse null (müşteri hesabında indirim bilgisi gösterilmez).
            'dealer' => $account?->isDealer() ? [
                'group' => $account->dealerGroup?->name,
                'rate' => $account->dealerDiscountRate(),
            ] : null,
            // Vitrin bakımdaysa "Mağazaya git" gösterilmez.
            // Vitrin modülü varsa (ve bakımda değilse) mağaza adresi; yoksa null.
            'shopUrl' => hooks()->apply('shop.storefront_url', null),
        ]);
    }
}
