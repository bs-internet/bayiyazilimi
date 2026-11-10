<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Services\Shop\ShopSettings;
use App\Support\Shop\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar › Satış Ayarları: fiyat gösterimi, teklif geçerlilik ve iade süreleri
 * (ShopSettings::SALES_KEYS).
 */
class SalesSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('panel/tools/definitions/sales/index', [
            'settings' => array_intersect_key(ShopSettings::all(), array_flip(ShopSettings::SALES_KEYS)),
            // Ziyaretçi / müşteri fiyat gösterimi yalnız vitrinde anlamlı; yoksa alan gizli.
            'storefront' => Storefront::enabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $storefront = Storefront::enabled();

        $data = $request->validate([
            // Vitrin yokken alan formda yoktur; kayıtlı değere dokunulmaz.
            'price_display' => $storefront ? ['required', Rule::in(['gross', 'net'])] : ['exclude'],
            'dealer_price_display' => ['required', Rule::in(['gross', 'net'])],
            'quote_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'return_days' => ['required', 'integer', 'min:0', 'max:365'],
            'order_notification_email' => ['nullable', 'email', 'max:255'],
        ], [], [
            'order_notification_email' => __('bildirim e-postası'),
            'price_display' => __('fiyat gösterimi'),
            'dealer_price_display' => __('bayi fiyat gösterimi'),
            'quote_valid_days' => __('teklif geçerlilik süresi'),
            'return_days' => __('iade talebi süresi'),
        ]);

        ShopSettings::save($data);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('satış ayarlarını güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Satış ayarları kaydedildi.')]);
    }
}
