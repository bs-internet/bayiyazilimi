<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Tools\Definitions;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Tools\Definitions\SaveCurrencyRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Tools\Definitions\CurrencyResource;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\Panel\Tools\Definitions\CurrencyService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        // Bir modül para birimi ekranını kendi (genişletilmiş) ekranıyla
        // değiştirebilir: filtre o ekranın adresini döndürür.
        $replacement = hooks()->apply('panel.definitions.currencies.index_url', null);
        if (is_string($replacement) && $replacement !== '') {
            // Kaydet/sil sonrası gelen bildirim (toast) yönlendirmede kaybolmasın.
            $request->session()->reflash();

            return redirect()->to($replacement);
        }

        $currencies = Currency::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(Pagination::PER_PAGE);

        return Inertia::render('panel/tools/definitions/currency/index', [
            'currencies' => PaginatedResource::make($currencies, CurrencyResource::class, $request),
            'defaults' => [
                'default_currency_id' => Setting::get('default_currency_id'),
            ],
        ]);
    }

    public function deleted(Request $request): Response
    {
        $currencies = Currency::query()
            ->onlyTrashed()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(Pagination::PER_PAGE);

        return Inertia::render('panel/tools/definitions/currency/deleted', [
            'currencies' => PaginatedResource::make($currencies, CurrencyResource::class, $request),
        ]);
    }

    public function store(SaveCurrencyRequest $request, CurrencyService $service): RedirectResponse
    {
        $currency = $service->create($request->validated(), $request->user());

        return to_route('panel.tools.definitions.currencies.index')
            ->with('toast', ['type' => 'success', 'message' => __(':code para birimi oluşturuldu.', ['code' => $currency->code])]);
    }

    public function update(SaveCurrencyRequest $request, Currency $currency, CurrencyService $service): RedirectResponse
    {
        $service->update($currency, $request->validated(), $request->user());

        return to_route('panel.tools.definitions.currencies.index')
            ->with('toast', ['type' => 'success', 'message' => __(':code para birimi güncellendi.', ['code' => $currency->code])]);
    }

    public function destroy(Request $request, Currency $currency, CurrencyService $service): RedirectResponse
    {
        $code = $currency->code;

        $service->delete($currency, $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':code para birimi silindi.', ['code' => $code])]);
    }

    public function status(Request $request, Currency $currency, CurrencyService $service): RedirectResponse
    {
        $wasActive = $currency->status === Status::Active;
        $wasActive
            ? $service->deactivate($currency, $request->user())
            : $service->activate($currency, $request->user());

        $action = $wasActive ? __('pasifleştirildi') : __('aktifleştirildi');

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':code para birimi :action.', ['code' => $currency->code, 'action' => $action])]);
    }

    public function restore(Request $request, string $currency, CurrencyService $service): RedirectResponse
    {
        $model = Currency::onlyTrashed()->findOrFail($currency);

        $service->restore($model, $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':code para birimi geri alındı.', ['code' => $model->code])]);
    }

    public function forceDelete(Request $request, string $currency, CurrencyService $service): RedirectResponse
    {
        $model = Currency::onlyTrashed()->findOrFail($currency);

        $code = $model->code;

        $service->forceDelete($model, $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':code para birimi tamamen silindi.', ['code' => $code])]);
    }
}
