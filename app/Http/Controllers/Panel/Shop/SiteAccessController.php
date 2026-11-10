<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\UpdateSiteAccessRequest;
use App\Services\Shop\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Genel Ayarlar › Site Erişimi: bakım modu (ShopSettings::ACCESS_KEYS). Sitenin
 * ziyaretçiye açık olup olmadığını ayar değil, vitrin modülünün varlığı belirler.
 */
class SiteAccessController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('panel/settings/general/access', [
            'settings' => array_intersect_key(ShopSettings::all(), array_flip(ShopSettings::ACCESS_KEYS)),
        ]);
    }

    public function update(UpdateSiteAccessRequest $request): RedirectResponse
    {
        ShopSettings::save($request->validated());

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('site erişim ayarlarını güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Site erişimi kaydedildi.')]);
    }
}
