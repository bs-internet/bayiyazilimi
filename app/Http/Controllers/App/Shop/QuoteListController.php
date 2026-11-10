<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Http\Controllers\Shop\QuoteListController as BaseQuoteListController;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bayi paneli teklif listesi (`/app/teklif-listesi`); talep "Tekliflerim"de izlenir.
 */
class QuoteListController extends BaseQuoteListController
{
    protected function routePrefix(): string
    {
        return 'app.shop.';
    }

    protected function page(): string
    {
        return 'app/shop/quote-list/index';
    }

    protected function added(Product $product): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => __(':name teklif listenize eklendi.', ['name' => $product->name])]);
    }

    protected function submitted(Quote $quote, Request $request): RedirectResponse
    {
        return to_route('app.shop.quotes.index')->with('toast', [
            'type' => 'success',
            'message' => __('Teklif talebiniz alındı. Talep numaranız: :number', ['number' => $quote->number]),
        ]);
    }
}
