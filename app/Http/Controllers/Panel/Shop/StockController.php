<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\StockMovementType;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Requests\Panel\Shop\StockOperationRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Shop\StockMovementResource;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    use BuildsShopOptions;

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'stock', 'warehouse', 'category']);
        $warehouses = Warehouse::query()->ordered()->get(['id', 'code', 'name', 'is_sellable', 'status']);

        $today = CarbonImmutable::today();
        $warnUntil = $today->addDays(ShopSettings::int('expiry_warning_days'));
        $lotsWithStock = fn (Builder $q) => $q->where('quantity', '>', 0);

        $products = Product::query()
            ->with([
                'stocks:id,product_id,warehouse_id,quantity',
                'locationStocks' => fn ($q) => $q->where('quantity', '!=', 0)->with(['location:id,code,is_unassigned', 'lot:id,code,expires_at']),
                'images',
                'category:id,name',
            ])
            // SKT'si yaklaşan / geçmiş lotu stokta olan ürünler.
            ->when(($filters['stock'] ?? null) === 'expiring', fn ($q) => $q->whereHas('locationStocks', fn (Builder $s) => $lotsWithStock($s)
                ->whereHas('lot', fn (Builder $l) => $l->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $warnUntil))))
            ->when(($filters['stock'] ?? null) === 'expired', fn ($q) => $q->whereHas('locationStocks', fn (Builder $s) => $lotsWithStock($s)
                ->whereHas('lot', fn (Builder $l) => $l->whereDate('expires_at', '<', $today))))
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(($filters['stock'] ?? null) === 'in', fn ($q) => $q->where('stock', '>', 0))
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->where('stock', '<=', 0))
            ->when(($filters['stock'] ?? null) === 'negative', fn ($q) => $q->whereHas('stocks', fn (Builder $s) => $s->where('quantity', '<', 0)))
            ->when(($filters['stock'] ?? null) === 'low', fn ($q) => $q->lowStock())
            ->when($filters['warehouse'] ?? null, fn ($q, $w) => $q->whereHas('stocks', fn (Builder $s) => $s->where('warehouse_id', $w)->where('quantity', '!=', 0)))
            ->orderBy('name')
            ->paginate(Pagination::PER_PAGE, ['id', 'sku', 'name', 'stock', 'min_stock', 'track_stock', 'tracking', 'expiry_tracking', 'status', 'category_id'])
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'image' => $p->images->first()?->url(),
                'category' => $p->category?->name,
                'stock' => $p->stock,
                'track_stock' => $p->track_stock,
                'tracking' => $p->trackingMode()->value,
                'expiry_tracking' => $p->tracksExpiry(),
                'low_stock' => $p->isLowStock(),
                'threshold' => $p->lowStockThreshold(),
                'quantities' => $p->stocks->pluck('quantity', 'warehouse_id')->map(fn ($q) => (int) $q)->all(),
                'total' => (int) $p->stocks->sum('quantity'),
                'locations' => $p->locationStocks
                    ->sortBy(fn (LocationStock $s) => ($s->lot?->expires_at?->format('Ymd') ?? '99999999').$s->location?->code)
                    ->map(fn (LocationStock $s) => [
                        'location_id' => $s->warehouse_location_id,
                        'warehouse_id' => $s->warehouse_id,
                        'code' => $s->location?->label(),
                        'lot_id' => $s->stock_lot_id,
                        'lot' => $s->lot?->code,
                        'expires_at' => $s->lot?->expires_at?->toDateString(),
                        'expired' => (bool) $s->lot?->isExpired($today),
                        'expiring' => $s->lot?->expires_at !== null && ! $s->lot->isExpired($today) && $s->lot->expires_at->lte($warnUntil),
                        'quantity' => $s->quantity,
                    ])->values()->all(),
            ]);

        return Inertia::render('panel/shop/stock/index', [
            'products' => $products,
            'warehouses' => $warehouses->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'is_sellable' => $w->is_sellable,
                'is_active' => $w->isActive(),
            ])->all(),
            'categories' => $this->categoryOptions(),
            'locations' => WarehouseLocation::query()->active()->pickOrder()->get(['id', 'warehouse_id', 'code', 'is_unassigned'])
                ->map(fn (WarehouseLocation $l) => ['id' => $l->id, 'warehouse_id' => $l->warehouse_id, 'code' => $l->label()])
                ->all(),
            'filters' => $filters,
            'traceability' => ShopSettings::traceability(),
        ]);
    }

    public function movements(Request $request): Response
    {
        $filters = $request->only(['q', 'warehouse', 'type', 'product', 'lot']);

        $movements = StockMovement::query()
            ->with(['product:id,sku,name', 'warehouse:id,name', 'location:id,code,is_unassigned', 'lot:id,code,expires_at', 'user:id,name'])
            ->when($filters['lot'] ?? null, fn ($q, $v) => $q->where('stock_lot_id', $v))
            ->when($filters['warehouse'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['product'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn (Builder $w) => $w
                ->whereHas('product', fn (Builder $p) => $p->withTrashed()->search($v))
                ->orWhereHas('lot', fn (Builder $l) => $l->where('code', 'like', '%'.mb_strtoupper($v).'%'))))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        return Inertia::render('panel/shop/stock/movements', [
            'movements' => PaginatedResource::make($movements, StockMovementResource::class, $request),
            'filters' => $filters,
            'warehouses' => $this->warehouseOptions(),
            'types' => array_map(fn (StockMovementType $t) => ['value' => $t->value, 'label' => $t->label()], StockMovementType::cases()),
            'product' => isset($filters['product'])
                ? Product::withTrashed()->whereKey($filters['product'])->first(['id', 'sku', 'name'])
                : null,
            'lot' => isset($filters['lot'])
                ? StockLot::query()->with('product:id,sku')->whereKey($filters['lot'])->first()?->only(['id', 'code'])
                : null,
        ]);
    }

    public function adjust(StockOperationRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        $quantity = (int) $data['quantity'];
        $note = $data['note'] ?? null;
        $location = isset($data['location_id']) ? WarehouseLocation::query()->findOrFail($data['location_id']) : null;

        // Lot kaydı işlemle aynı transaction'da: işlem başarısız olursa sahipsiz lot kalmaz.
        DB::transaction(function () use ($stock, $data, $product, $warehouse, $quantity, $note, $location, $request) {
            $lot = $this->lotFor($product, $data);

            // Elle çıkış eldeki miktarı aşamaz; denetim ve düşüm StockService::issue'da aynı kilit altında.
            match ($data['operation']) {
                'in' => $stock->change($product, $warehouse, $quantity, StockMovementType::In, $note, $request->user(), null, true, $location, $lot),
                'out' => $stock->issue($product, $warehouse, $quantity, $note, $request->user(), $location, $lot),
                'count' => $stock->setQuantity($product, $warehouse, $quantity, $note, $request->user(), StockMovementType::Adjust, $location, $lot),
            };
        });

        return back()->with('toast', ['type' => 'success', 'message' => __(':sku stoğu güncellendi.', ['sku' => $product->sku])]);
    }

    public function transfer(StockOperationRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);

        $stock->transfer(
            $product,
            Warehouse::query()->findOrFail($data['from_warehouse_id']),
            Warehouse::query()->findOrFail($data['to_warehouse_id']),
            (int) $data['quantity'],
            $data['note'] ?? null,
            $request->user(),
            isset($data['to_location_id']) ? WarehouseLocation::query()->findOrFail($data['to_location_id']) : null,
            isset($data['from_location_id']) ? WarehouseLocation::query()->findOrFail($data['from_location_id']) : null,
            $this->lotFor($product, $data),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __(':sku transferi tamamlandı.', ['sku' => $product->sku])]);
    }

    /**
     * Aynı depoda lokasyon değişimi.
     */
    public function relocate(StockOperationRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);

        $stock->relocate(
            $product,
            WarehouseLocation::query()->findOrFail($data['from_location_id']),
            WarehouseLocation::query()->findOrFail($data['to_location_id']),
            (int) $data['quantity'],
            $data['note'] ?? null,
            $request->user(),
            $this->lotFor($product, $data),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __(':sku lokasyonu değiştirildi.', ['sku' => $product->sku])]);
    }

    /**
     * Stok › Lot / Seri No: stokta (ya da hareketi olan) lotlar, SKT ve depo
     * dağılımıyla; hareketler ekranına (izlenebilirlik) bağlanır.
     */
    public function lots(Request $request): Response
    {
        $filters = $request->only(['q', 'state', 'warehouse']);
        $today = CarbonImmutable::today();
        $warnUntil = $today->addDays(ShopSettings::int('expiry_warning_days'));
        $inStock = fn (Builder $s) => $s->where('quantity', '>', 0)
            ->when($filters['warehouse'] ?? null, fn (Builder $w, $v) => $w->where('warehouse_id', $v));

        $lots = StockLot::query()
            ->with([
                'product:id,sku,name,tracking,unit',
                'stocks' => fn ($q) => $q->where('quantity', '!=', 0)->with(['warehouse:id,name', 'location:id,code,is_unassigned']),
            ])
            ->withSum(['stocks as quantity' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->when($filters['q'] ?? null, fn (Builder $q, $v) => $q->where(fn (Builder $w) => $w
                ->where('code', 'like', '%'.mb_strtoupper($v).'%')
                ->orWhereHas('product', fn (Builder $p) => $p->withTrashed()->search($v))))
            ->when(($filters['state'] ?? 'in_stock') === 'in_stock', fn ($q) => $q->whereHas('stocks', $inStock))
            ->when(($filters['state'] ?? null) === 'expiring', fn ($q) => $q->whereHas('stocks', $inStock)
                ->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $warnUntil))
            ->when(($filters['state'] ?? null) === 'expired', fn ($q) => $q->whereHas('stocks', $inStock)->whereDate('expires_at', '<', $today))
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->orderBy('code')
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockLot $lot) => [
                'id' => $lot->id,
                'code' => $lot->code,
                'kind' => $lot->product?->trackingMode()->label(),
                'product_id' => $lot->product_id,
                'sku' => $lot->product?->sku,
                'name' => $lot->product?->name,
                'unit' => $lot->product?->unit,
                'expires_at' => $lot->expires_at?->toDateString(),
                'expired' => $lot->isExpired($today),
                'expiring' => $lot->expires_at !== null && ! $lot->isExpired($today) && $lot->expires_at->lte($warnUntil),
                'quantity' => (int) $lot->quantity,
                'places' => $lot->stocks->map(fn (LocationStock $s) => [
                    'warehouse' => $s->warehouse?->name,
                    'location' => $s->location?->label(),
                    'quantity' => $s->quantity,
                ])->values()->all(),
            ]);

        return Inertia::render('panel/shop/stock/lots', [
            'lots' => $lots,
            'filters' => $filters,
            'warehouses' => $this->warehouseOptions(),
            'warningDays' => ShopSettings::int('expiry_warning_days'),
        ]);
    }

    /**
     * İşlem formundaki lot: seçilen lot (lot_id) ya da girişte yazılan lot / seri no (lot_code).
     *
     * @param  array<string, mixed>  $data
     */
    private function lotFor(Product $product, array $data): ?StockLot
    {
        if (! $product->tracksLots()) {
            return null;
        }

        if (! empty($data['lot_id'])) {
            return StockLot::query()->where('product_id', $product->id)->findOrFail($data['lot_id']);
        }

        if (! empty($data['lot_code'])) {
            $lot = StockLot::query()->firstOrCreate(
                ['product_id' => $product->id, 'code' => mb_strtoupper(trim((string) $data['lot_code']))],
                ['expires_at' => $data['expires_at'] ?? null],
            );

            if (! empty($data['expires_at'])) {
                if ($lot->expires_at === null) {
                    $lot->update(['expires_at' => $data['expires_at']]);
                } elseif ($lot->expires_at->toDateString() !== CarbonImmutable::parse($data['expires_at'])->toDateString()) {
                    // Var olan lotun SKT'si sessizce yok sayılmasın.
                    throw new ShopException(__(':code lotu :date SKT ile kayıtlı; farklı SKT girilemez.', ['code' => $lot->code, 'date' => $lot->expires_at->format('d.m.Y')]));
                }
            }

            return $lot;
        }

        return null;
    }
}
