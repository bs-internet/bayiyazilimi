<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveWarehouseRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Shop\StockMovementResource;
use App\Http\Resources\Panel\Shop\WarehouseResource;
use App\Models\LocationStock;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Panel\Shop\CatalogService;
use App\Services\Shop\WarehouseStockExporter;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $warehouses = Warehouse::query()->withSum('stocks', 'quantity')->ordered()->get();

        return Inertia::render('panel/shop/warehouses/index', [
            'warehouses' => WarehouseResource::collection($warehouses)->resolve($request),
        ]);
    }

    /**
     * Depo detayı: lokasyonlar, depodaki ürünler (lokasyon dağılımıyla) ve
     * depoya ait stok hareketleri. `lokasyon` ile ürün listesi süzülür.
     */
    public function show(Request $request, Warehouse $warehouse): Response
    {
        $warehouse->unassignedLocation();

        $q = trim((string) $request->query('q', ''));
        $locationId = $request->query('lokasyon');
        $locationId = is_string($locationId) && $warehouse->locations()->whereKey($locationId)->exists() ? $locationId : null;

        $stocks = ProductStock::query()
            ->where('product_stocks.warehouse_id', $warehouse->id)
            ->where('product_stocks.quantity', '!=', 0)
            ->whereHas('product', fn (Builder $p) => $p->search($q !== '' ? $q : null))
            ->when($locationId, fn (Builder $query, string $id) => $query->whereExists(fn ($sub) => $sub
                ->from('location_stocks')
                ->whereColumn('location_stocks.product_id', 'product_stocks.product_id')
                ->where('location_stocks.warehouse_location_id', $id)
                ->where('location_stocks.quantity', '!=', 0)))
            ->with([
                'product:id,sku,name,category_id,tracking,expiry_tracking',
                'product.images',
                'product.category:id,name',
                'product.stocks:id,product_id,warehouse_id,quantity',
                'product.locationStocks' => fn ($q) => $q->where('quantity', '!=', 0)
                    ->with(['location:id,code,is_unassigned,sort_order', 'lot:id,code,expires_at']),
            ])
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->orderBy('products.name')
            ->select('product_stocks.*')
            ->paginate(Pagination::PER_PAGE, pageName: 'urun')
            ->withQueryString()
            ->through(fn (ProductStock $s) => [
                'product_id' => $s->product_id,
                'sku' => $s->product?->sku,
                'name' => $s->product?->name,
                'image' => $s->product?->images->first()?->url(),
                'category' => $s->product?->category?->name,
                'quantity' => (int) $s->quantity,
                'tracking' => $s->product?->trackingMode()->value ?? 'none',
                'expiry_tracking' => (bool) $s->product?->tracksExpiry(),
                // Stok işlemi formu için: tüm depolardaki miktarlar ve lokasyon / lot dağılımı.
                'quantities' => $s->product?->stocks->pluck('quantity', 'warehouse_id')->map(fn ($q) => (int) $q)->all() ?? [],
                'locations' => $s->product?->locationStocks
                    ->sortBy(fn (LocationStock $l) => sprintf('%d|%010d|%s|%s', $l->location?->is_unassigned ? 1 : 0, $l->location?->sort_order ?? 0, $l->location?->code, $l->lot?->code))
                    ->map(fn (LocationStock $l) => [
                        'location_id' => $l->warehouse_location_id,
                        'warehouse_id' => $l->warehouse_id,
                        'code' => $l->location?->label(),
                        'lot_id' => $l->stock_lot_id,
                        'lot' => $l->lot?->code,
                        'expires_at' => $l->lot?->expires_at?->toDateString(),
                        'expired' => (bool) $l->lot?->isExpired(),
                        'quantity' => $l->quantity,
                    ])->values()->all() ?? [],
            ]);

        $movements = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->with(['product:id,sku,name', 'warehouse:id,name', 'location:id,code,is_unassigned', 'lot:id,code,expires_at', 'user:id,name'])
            ->latest()
            ->paginate(Pagination::PER_PAGE, pageName: 'hareket')
            ->withQueryString();

        $usage = LocationStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '!=', 0)
            ->selectRaw('warehouse_location_id, count(*) as products, sum(quantity) as quantity')
            ->groupBy('warehouse_location_id')
            ->get()
            ->keyBy('warehouse_location_id');

        $locations = $warehouse->locations()->pickOrder()->get()->map(fn (WarehouseLocation $l) => [
            'id' => $l->id,
            'code' => $l->label(),
            'aisle' => $l->aisle,
            'rack' => $l->rack,
            'bin' => $l->bin,
            'is_unassigned' => $l->is_unassigned,
            'status' => $l->status,
            'products' => (int) ($usage[$l->id]->products ?? 0),
            'quantity' => (int) ($usage[$l->id]->quantity ?? 0),
        ])->all();

        $totals = ProductStock::query()->where('warehouse_id', $warehouse->id)
            ->selectRaw('count(case when quantity != 0 then 1 end) as products, coalesce(sum(quantity), 0) as quantity')
            ->first();

        return Inertia::render('panel/shop/warehouses/show', [
            'warehouse' => WarehouseResource::make($warehouse)->resolve($request),
            'stocks' => $stocks,
            'movements' => PaginatedResource::make($movements, StockMovementResource::class, $request),
            'locations' => $locations,
            // Stok işlemi formu (transfer / lokasyon değişimi) seçenekleri.
            'allWarehouses' => Warehouse::query()->active()->ordered()->get(['id', 'name'])
                ->map(fn (Warehouse $w) => ['id' => $w->id, 'name' => $w->name])->all(),
            'allLocations' => WarehouseLocation::query()->active()->pickOrder()->get(['id', 'warehouse_id', 'code', 'is_unassigned'])
                ->map(fn (WarehouseLocation $l) => ['id' => $l->id, 'warehouse_id' => $l->warehouse_id, 'code' => $l->label()])->all(),
            'totals' => [
                'products' => (int) ($totals->products ?? 0),
                'quantity' => (int) ($totals->quantity ?? 0),
                'movements' => $movements->total(),
                'locations' => count($locations) - 1,
            ],
            'q' => $q,
            'locationFilter' => $locationId,
        ]);
    }

    /**
     * Yalnızca bu depodaki stokları Excel'e aktarır.
     */
    public function export(Request $request, Warehouse $warehouse, WarehouseStockExporter $exporter): BinaryFileResponse
    {
        $path = $exporter->export($warehouse);

        CatalogChangedEvent::dispatch($warehouse, 'warehouse', 'exported', __(':name deposunun stoklarını Excel\'e aktardı.', ['name' => $warehouse->name]), $request->user());

        return response()->download($path, 'depo-'.Str::slug($warehouse->code).'-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function store(SaveWarehouseRequest $request, CatalogService $catalog): RedirectResponse
    {
        $warehouse = $catalog->saveWarehouse(null, $request->payload(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name deposu oluşturuldu.', ['name' => $warehouse->name])]);
    }

    public function update(SaveWarehouseRequest $request, Warehouse $warehouse, CatalogService $catalog): RedirectResponse
    {
        $catalog->saveWarehouse($warehouse, $request->payload(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name deposu güncellendi.', ['name' => $warehouse->name])]);
    }

    public function destroy(Request $request, Warehouse $warehouse, CatalogService $catalog): RedirectResponse
    {
        $catalog->deleteWarehouse($warehouse, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name deposu silindi.', ['name' => $warehouse->name])]);
    }
}
