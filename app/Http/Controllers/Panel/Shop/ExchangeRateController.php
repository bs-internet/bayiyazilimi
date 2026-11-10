<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\RateSource;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\Shop\ExchangeRateService;
use App\Services\Shop\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(ExchangeRateService $service): Response
    {
        $defaultId = Setting::get('default_currency_id');

        return Inertia::render('panel/tools/definitions/exchange-rates/index', [
            'currencies' => Currency::query()->ordered()->orderBy('code')->get()->map(fn (Currency $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
                'decimal_places' => $c->decimal_places,
                'thousands_separator' => $c->thousands_separator,
                'decimal_separator' => $c->decimal_separator,
                'sort_order' => $c->sort_order,
                'status' => $c->status,
                'exchange_rate' => $c->exchange_rate,
                'rate_source' => $c->rate_source->value,
                'rate_updated_at' => $c->rate_updated_at,
                'is_default' => $c->id === $defaultId,
            ])->all(),
            'sources' => array_map(fn (RateSource $s) => ['value' => $s->value, 'label' => $s->label()], RateSource::cases()),
            'rateType' => ShopSettings::get('tcmb_rate_type'),
            'rateTypes' => collect(ExchangeRateService::rateTypes())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'autoUpdate' => ShopSettings::bool('tcmb_auto_update'),
            // "Para birimi ekle" penceresi açılınca yüklenir (TCMB isteği sayfayı yavaşlatmasın).
            'available' => Inertia::optional(fn () => $service->availableTcmbCurrencies()),
        ]);
    }

    /**
     * TCMB ayarları: kullanılan kur tipi ve otomatik güncelleme (ShopSettings::RATE_KEYS).
     */
    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tcmb_rate_type' => ['required', Rule::in(array_keys(ExchangeRateService::rateTypes()))],
            'tcmb_auto_update' => ['boolean'],
        ]);

        ShopSettings::save(['tcmb_rate_type' => $data['tcmb_rate_type'], 'tcmb_auto_update' => $request->boolean('tcmb_auto_update')]);

        return back()->with('toast', ['type' => 'success', 'message' => __('TCMB ayarları kaydedildi.')]);
    }

    public function store(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha'],
        ]);

        $currency = $service->addTcmbCurrency($data['code'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':code para birimi eklendi.', ['code' => $currency->code])]);
    }

    public function update(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $data = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'rates.*.rate_source' => ['required', Rule::enum(RateSource::class)],
        ]);

        $service->saveManual($data['rates'], $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Döviz kurları kaydedildi.')]);
    }

    public function sync(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $updated = $service->syncFromTcmb($request->user());

        $message = $updated === []
            ? __('TCMB kaynaklı para birimi bulunamadı ya da kur değişmedi.')
            : __('TCMB kurları güncellendi: :rates', ['rates' => collect($updated)->map(fn ($r, $c) => $c.' '.$r)->implode(', ')]);

        return back()->with('toast', ['type' => $updated === [] ? 'info' : 'success', 'message' => $message]);
    }
}
