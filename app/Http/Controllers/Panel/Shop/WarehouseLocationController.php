<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Depo detayındaki lokasyon yönetimi (koridor / raf / göz).
 */
class WarehouseLocationController extends Controller
{
    public function store(Request $request, Warehouse $warehouse, LocationService $locations): RedirectResponse
    {
        $data = $this->validatePart($request);
        $location = $locations->create($warehouse, $data['aisle'] ?? null, $data['rack'] ?? null, $data['bin'] ?? null, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':code lokasyonu eklendi.', ['code' => $location->code])]);
    }

    public function bulk(Request $request, Warehouse $warehouse, LocationService $locations): RedirectResponse
    {
        $data = $request->validate([
            'aisles' => ['required', 'string', 'max:255'],
            'rack_from' => ['required', 'integer', 'min:0', 'max:999'],
            'rack_to' => ['required', 'integer', 'gte:rack_from', 'max:999'],
            'bin_from' => ['nullable', 'integer', 'min:0', 'max:99', 'required_with:bin_to'],
            'bin_to' => ['nullable', 'integer', 'gte:bin_from', 'max:99', 'required_with:bin_from'],
        ], [], [
            'aisles' => __('koridorlar'),
            'rack_from' => __('ilk raf'),
            'rack_to' => __('son raf'),
            'bin_from' => __('ilk göz'),
            'bin_to' => __('son göz'),
        ]);

        $created = $locations->bulkCreate(
            $warehouse,
            preg_split('/[\s,;]+/', $data['aisles']) ?: [],
            (int) $data['rack_from'],
            (int) $data['rack_to'],
            isset($data['bin_from']) ? (int) $data['bin_from'] : null,
            isset($data['bin_to']) ? (int) $data['bin_to'] : null,
            $request->user(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __(':created lokasyon oluşturuldu.', ['created' => $created])]);
    }

    public function update(Request $request, WarehouseLocation $location, LocationService $locations): RedirectResponse
    {
        $data = $this->validatePart($request);
        $locations->update($location, $data['aisle'] ?? null, $data['rack'] ?? null, $data['bin'] ?? null, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':code lokasyonu güncellendi.', ['code' => $location->code])]);
    }

    public function status(Request $request, WarehouseLocation $location, LocationService $locations): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($location->hasStatus($request->input('status'))) {
            return back();
        }

        $locations->toggleStatus($location, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':code lokasyonunun durumu değiştirildi.', ['code' => $location->code])]);
    }

    public function destroy(Request $request, WarehouseLocation $location, LocationService $locations): RedirectResponse
    {
        $locations->delete($location, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':code lokasyonu silindi.', ['code' => $location->code])]);
    }

    /**
     * @return array{aisle?: ?string, rack?: ?string, bin?: ?string}
     */
    private function validatePart(Request $request): array
    {
        $part = ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN]+$/u'];

        return $request->validate([
            'aisle' => $part,
            'rack' => $part,
            'bin' => $part,
        ], [], ['aisle' => __('koridor'), 'rack' => __('raf'), 'bin' => __('göz')]);
    }
}
