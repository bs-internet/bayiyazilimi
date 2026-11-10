<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveCarrierRequest;
use App\Http\Requests\Panel\Shop\UpdateCarrierRatesRequest;
use App\Http\Resources\Panel\Shop\CarrierResource;
use App\Models\Carrier;
use App\Services\Panel\Shop\CarrierService;
use App\Services\Shop\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CarrierController extends Controller
{
    public function index(Request $request): Response
    {
        $carriers = Carrier::query()->withCount('rates')->orderByDesc('is_default')->ordered()->get();

        return Inertia::render('panel/tools/definitions/carriers/index', [
            'carriers' => CarrierResource::collection($carriers)->resolve($request),
        ]);
    }

    public function store(SaveCarrierRequest $request, CarrierService $service): RedirectResponse
    {
        $carrier = $service->save(null, $request->payload(), $request->file('logo'), false, $request->user());

        return to_route('panel.shop.carriers.edit', $carrier->id)
            ->with('toast', ['type' => 'success', 'message' => __(':name eklendi. Şimdi desi fiyatlarını girin.', ['name' => $carrier->name])]);
    }

    public function edit(Request $request, Carrier $carrier): Response
    {
        $carrier->load('rates');

        return Inertia::render('panel/tools/definitions/carriers/edit', [
            'carrier' => CarrierResource::make($carrier)->resolve($request),
        ]);
    }

    public function update(SaveCarrierRequest $request, Carrier $carrier, CarrierService $service): RedirectResponse
    {
        $service->save($carrier, $request->payload(), $request->file('logo'), $request->boolean('remove_logo'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name güncellendi.', ['name' => $carrier->name])]);
    }

    public function status(Request $request, Carrier $carrier, CarrierService $service): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($carrier->hasStatus($request->input('status'))) {
            return back();
        }

        $service->toggleStatus($carrier, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name durumu değiştirildi.', ['name' => $carrier->name])]);
    }

    public function destroy(Request $request, Carrier $carrier, CarrierService $service): RedirectResponse
    {
        $service->delete($carrier, $request->user());

        return to_route('panel.shop.carriers.index')
            ->with('toast', ['type' => 'success', 'message' => __(':name silindi.', ['name' => $carrier->name])]);
    }

    public function updateRates(UpdateCarrierRatesRequest $request, Carrier $carrier, ShippingService $shipping): RedirectResponse
    {
        $count = $shipping->saveRates($carrier, $request->validated('rates', []), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Desi tablosu kaydedildi (:count satır).', ['count' => $count])]);
    }

    public function importRates(Request $request, Carrier $carrier, ShippingService $shipping): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $count = $shipping->importRates($carrier, $request->file('file')->getRealPath(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Excel\'den :count desi satırı yüklendi.', ['count' => $count])]);
    }

    public function template(Request $request, ShippingService $shipping): BinaryFileResponse
    {
        $carrier = $request->filled('carrier') ? Carrier::query()->find($request->string('carrier')->toString()) : null;
        $path = $shipping->template($carrier);
        $name = 'desi-fiyatlari'.($carrier ? '-'.strtolower($carrier->code) : '').'.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend();
    }
}
