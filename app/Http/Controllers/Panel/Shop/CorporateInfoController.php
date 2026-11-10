<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\UpdateCorporateInfoRequest;
use App\Models\Country;
use App\Services\Shop\ShopSettings;
use App\Services\Support\FileService;
use App\Support\Shop\Storefront;
use App\Support\Shop\SystemDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Genel Ayarlar › Kurumsal Bilgiler: firma ünvanı, vergi / MERSİS, adres
 * (ShopSettings::CORPORATE_KEYS) ve vitrin alt bilgisindeki ETBİS kare kodu.
 */
class CorporateInfoController extends Controller
{
    private const ETBIS_DIRECTORY = 'media';

    public function edit(): Response
    {
        $settings = array_intersect_key(ShopSettings::all(), array_flip(ShopSettings::CORPORATE_KEYS));
        $settings['store_country_id'] = $settings['store_country_id'] ?: SystemDefaults::countryId();

        return Inertia::render('panel/settings/general/corporate', [
            'settings' => $settings,
            'etbisQrUrl' => ShopSettings::etbisQrUrl(),
            // ETBİS kare kodu vitrinin alt bilgisinde gösterilir; yalnız vitrin kuruluyken.
            'storefront' => Storefront::enabled(),
            'countries' => Country::query()->where('status', Status::Active->value)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCorporateInfoRequest $request): RedirectResponse
    {
        ShopSettings::save(Storefront::enabled() ? $request->validated() : $request->safe()->except('etbis_url'));

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('kurumsal bilgileri güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Kurumsal bilgiler kaydedildi.')]);
    }

    /**
     * ETBİS'ten indirilen kare kod görselini anında yükler; eskisini siler.
     */
    public function uploadEtbis(Request $request, FileService $files): RedirectResponse
    {
        // Kare kod vitrinin alt bilgisinde gösterilir; vitrin yokken alan da yoktur.
        abort_unless(Storefront::enabled(), 404);

        $request->validate(
            ['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024']],
            [],
            ['file' => __('ETBİS kare kodu')],
        );

        $path = $files->storePublicImage(
            $request->file('file'),
            self::ETBIS_DIRECTORY,
            ShopSettings::get('etbis_qr_path') ?: null,
            'etbis',
        );

        ShopSettings::save(['etbis_qr_path' => $path]);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('ETBİS kare kodunu güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('ETBİS kare kodu yüklendi.')]);
    }

    public function destroyEtbis(Request $request, FileService $files): RedirectResponse
    {
        abort_unless(Storefront::enabled(), 404);

        $files->deletePublicFile(ShopSettings::get('etbis_qr_path') ?: null);

        ShopSettings::save(['etbis_qr_path' => '']);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('ETBİS kare kodunu kaldırdı.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('ETBİS kare kodu kaldırıldı.')]);
    }
}
