<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\UpdateShippingSettingsRequest;
use App\Services\Shop\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar › Kargo Ayarları: sabit ücret, ücretsiz kargo eşiği, varsayılan
 * ürün desisi (ShopSettings::SHIPPING_KEYS). Desi fiyatları Kargo Firmaları'nda.
 */
class ShippingSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('panel/tools/definitions/shipping/index', [
            'settings' => array_intersect_key(ShopSettings::all(), array_flip(ShopSettings::SHIPPING_KEYS)),
        ]);
    }

    public function update(UpdateShippingSettingsRequest $request): RedirectResponse
    {
        ShopSettings::save($request->validated());

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('kargo ayarlarını güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Kargo ayarları kaydedildi.')]);
    }
}
