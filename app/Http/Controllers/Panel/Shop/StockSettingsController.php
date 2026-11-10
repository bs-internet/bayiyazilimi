<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Shop\ShopSettings;
use App\Support\Shop\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stok › Stok Ayarları: varsayılan satış deposu (tek kaynak `warehouses.is_default`)
 * ve stokta olmayan ürünlerin vitrinde gösterimi (yalnız vitrin kuruluyken).
 */
class StockSettingsController extends Controller
{
    use BuildsShopOptions;

    public function edit(): Response
    {
        return Inertia::render('panel/shop/stock/settings', [
            'defaultWarehouseId' => Warehouse::default()?->id,
            'showOutOfStock' => ShopSettings::bool('show_out_of_stock'),
            // "Stokta olmayanları vitrinde göster" yalnız vitrin kuruluyken anlamlıdır.
            'storefront' => Storefront::enabled(),
            'lowStockThreshold' => ShopSettings::int('low_stock_threshold'),
            'lowStockNotify' => ShopSettings::bool('low_stock_notify'),
            'traceability' => ShopSettings::traceability(),
            'expiryWarningDays' => ShopSettings::int('expiry_warning_days'),
            'stockReservationDays' => ShopSettings::int('stock_reservation_days'),
            'warehouses' => $this->warehouseOptions(onlyActive: true),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('status', Status::Active->value)],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'expiry_warning_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'stock_reservation_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], [], ['default_warehouse_id' => __('varsayılan satış deposu'), 'low_stock_threshold' => __('kritik stok eşiği'), 'expiry_warning_days' => __('SKT uyarı süresi'), 'stock_reservation_days' => __('stok rezervasyon süresi')]);

        DB::transaction(function () use ($data) {
            Warehouse::query()->whereKeyNot($data['default_warehouse_id'])->update(['is_default' => false]);
            Warehouse::query()->whereKey($data['default_warehouse_id'])->update(['is_default' => true]);
        });

        $traceabilityChanged = ShopSettings::traceability() !== $request->boolean('traceability_enabled');
        $thresholdChanged = ShopSettings::int('low_stock_threshold') !== (int) $data['low_stock_threshold'];

        ShopSettings::save([
            ...(Storefront::enabled() ? ['show_out_of_stock' => $request->boolean('show_out_of_stock')] : []),
            'low_stock_threshold' => (string) (int) $data['low_stock_threshold'],
            'low_stock_notify' => $request->boolean('low_stock_notify'),
            'traceability_enabled' => $request->boolean('traceability_enabled'),
            'expiry_warning_days' => (string) (int) ($data['expiry_warning_days'] ?? 30),
            'stock_reservation_days' => (string) (int) ($data['stock_reservation_days'] ?? ShopSettings::int('stock_reservation_days')),
        ]);

        // İzlenebilirlik açılıp kapanınca süresi geçmiş lotlar satılabilir toplama girer /
        // çıkar; eşik değişince kritik stok bayrağı yeniden değerlendirilir. Ürün toplamları
        // bir sonraki stok hareketini beklemeden yeniden hesaplanır.
        if ($traceabilityChanged || $thresholdChanged) {
            Product::query()
                ->where('track_stock', true)
                ->when(! $thresholdChanged, fn ($q) => $q->whereHas('lots'))
                ->each(fn (Product $product) => $product->syncStockTotal());
        }

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('stok ayarlarını güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Stok ayarları kaydedildi.')]);
    }
}
