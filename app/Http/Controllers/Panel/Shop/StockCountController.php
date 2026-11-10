<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\StockCountStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Requests\Panel\Shop\StockCountLineRequest;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockCountService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stok › Sayım: barkodla sayım oturumu ve farkların toplu onayı.
 */
class StockCountController extends Controller
{
    use BuildsShopOptions;

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'warehouse']);

        $counts = StockCount::query()
            ->with(['warehouse:id,name', 'location:id,code,is_unassigned', 'user:id,name', 'closer:id,name'])
            ->withCount(['lines', 'movements'])
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['warehouse'] ?? null, fn (Builder $q, $v) => $q->where('warehouse_id', $v))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockCount $c) => [
                'id' => $c->id,
                'number' => $c->number,
                'warehouse' => $c->warehouse?->name,
                'location' => $c->location?->label(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'lines' => (int) $c->lines_count,
                'movements' => (int) $c->movements_count,
                'note' => $c->note,
                'user' => $c->user?->name,
                'closer' => $c->closer?->name,
                'created_at' => $c->created_at,
                'closed_at' => $c->closed_at,
            ]);

        return Inertia::render('panel/shop/stock-counts/index', [
            'counts' => $counts,
            'filters' => $filters,
            'statuses' => StockCountStatus::options(),
            'warehouses' => $this->warehouseOptions(),
            'activeWarehouses' => $this->warehouseOptions(onlyActive: true),
            'defaultWarehouseId' => Warehouse::default()?->id,
            'locations' => $this->locations(),
        ]);
    }

    public function store(Request $request, StockCountService $counts): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')],
            'location_id' => ['nullable', 'uuid', Rule::exists('warehouse_locations', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['warehouse_id' => 'depo', 'location_id' => 'lokasyon', 'note' => __('not')]);

        $count = $counts->open(
            Warehouse::query()->findOrFail($data['warehouse_id']),
            isset($data['location_id']) ? WarehouseLocation::query()->findOrFail($data['location_id']) : null,
            $data['note'] ?? null,
            $request->user(),
        );

        return to_route('panel.shop.stock-counts.show', $count->id)
            ->with('toast', ['type' => 'success', 'message' => __(':number sayımı başlatıldı.', ['number' => $count->number])]);
    }

    public function show(StockCount $stockCount, StockCountService $counts): Response
    {
        $stockCount->load(['warehouse:id,name,code', 'location:id,code,is_unassigned', 'user:id,name', 'closer:id,name']);

        // Büyük depoda binlerce satır olabilir: liste sayfalı, özet veritabanında toplanır.
        $rows = $stockCount->lines()
            ->with(['product:id,sku,barcode,name,unit,tracking', 'location:id,code,is_unassigned,sort_order', 'lot:id,code,expires_at', 'user:id,name'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(Pagination::PER_PAGE, pageName: 'satir')
            ->withQueryString()
            ->through(fn (StockCountLine $l) => [
                'id' => $l->id,
                'product_id' => $l->product_id,
                'sku' => $l->product?->sku,
                'barcode' => $l->product?->barcode,
                'name' => $l->product?->name,
                'unit' => $l->product?->unit,
                'serial' => (bool) $l->product?->tracksSerials(),
                'location' => $l->location?->label(),
                'lot' => $l->lot?->code,
                'expires_at' => $l->lot?->expires_at?->toDateString(),
                'expected' => $l->expected,
                'counted' => $l->counted,
                'difference' => $l->difference(),
                'user' => $l->user?->name,
                'updated_at' => $l->updated_at,
            ]);

        // "lines" MySQL / MariaDB'de ayrılmış sözcük: takma ad line_count (testler MySQL'de koştuğu için yakalanır).
        $summary = $stockCount->lines()->toBase()->selectRaw(
            'count(*) as line_count, coalesce(sum(counted), 0) as counted,'
            .' coalesce(sum(case when counted > expected then counted - expected else 0 end), 0) as surplus,'
            .' coalesce(sum(case when counted < expected then expected - counted else 0 end), 0) as shortage,'
            .' coalesce(sum(case when counted <> expected then 1 else 0 end), 0) as differing',
        )->first();

        // Sayılmayanlar: toplamlar tamamı üzerinden, liste ilk sayfa kadar.
        $uncounted = $stockCount->isOpen() ? $counts->uncounted($stockCount) : collect();

        $uncountedRows = $uncounted->take(Pagination::PER_PAGE)->map(fn (LocationStock $s) => [
            'id' => $s->id,
            'sku' => $s->product?->sku,
            'name' => $s->product?->name,
            'location' => $s->location?->label(),
            'lot' => $s->lot?->code,
            'quantity' => $s->quantity,
        ])->values();

        return Inertia::render('panel/shop/stock-counts/show', [
            'count' => [
                'id' => $stockCount->id,
                'number' => $stockCount->number,
                'warehouse' => $stockCount->warehouse ? __(':name (:code)', ['name' => $stockCount->warehouse->name, 'code' => $stockCount->warehouse->code]) : null,
                'location' => $stockCount->location?->label(),
                'status' => $stockCount->status->value,
                'status_label' => $stockCount->status->label(),
                'zero_uncounted' => $stockCount->zero_uncounted,
                'note' => $stockCount->note,
                'user' => $stockCount->user?->name,
                'closer' => $stockCount->closer?->name,
                'created_at' => $stockCount->created_at,
                'closed_at' => $stockCount->closed_at,
            ],
            'lines' => $rows,
            'summary' => [
                'lines' => (int) $summary->line_count,
                'counted' => (int) $summary->counted,
                'surplus' => (int) $summary->surplus,
                'shortage' => (int) $summary->shortage,
                'differing' => (int) $summary->differing,
                'uncounted' => $uncounted->count(),
                'uncounted_quantity' => (int) $uncounted->sum('quantity'),
            ],
            'uncounted' => $uncountedRows,
            'locations' => $stockCount->isOpen()
                ? $this->locations($stockCount->warehouse_id, $stockCount->warehouse_location_id)
                : [],
            'traceability' => ShopSettings::traceability(),
        ]);
    }

    /**
     * Barkod / SKU / ad ile ürün arama. Tam barkod ya da SKU eşleşmesi `exact` döner.
     */
    public function lookup(Request $request, StockCount $stockCount): JsonResponse
    {
        $code = trim($request->string('q')->toString());

        if ($code === '') {
            return response()->json(['data' => [], 'exact' => false]);
        }

        $columns = ['id', 'sku', 'barcode', 'name', 'unit', 'tracking', 'expiry_tracking'];

        $exact = Product::query()
            ->where(fn (Builder $q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->limit(2)
            ->get($columns);

        $products = $exact->count() === 1
            ? $exact
            : Product::query()->search($code)->orderBy('name')->limit(10)->get($columns);

        $lots = LocationStock::query()
            ->where('warehouse_id', $stockCount->warehouse_id)
            ->whereIn('product_id', $products->pluck('id'))
            ->whereNotNull('stock_lot_id')
            ->where('quantity', '>', 0)
            ->with('lot:id,code,expires_at')
            ->get()
            ->groupBy('product_id');

        return response()->json([
            'exact' => $exact->count() === 1,
            'data' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'unit' => $p->unit,
                'tracking' => $p->trackingMode()->value,
                'expiry_tracking' => $p->tracksExpiry(),
                'lots' => ($lots[$p->id] ?? collect())
                    ->pluck('lot')
                    ->filter()
                    ->unique('id')
                    ->map(fn ($lot) => ['code' => $lot->code, 'expires_at' => $lot->expires_at?->toDateString()])
                    ->values(),
            ])->values(),
        ]);
    }

    public function storeLine(StockCountLineRequest $request, StockCount $stockCount, StockCountService $counts): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);

        $line = $counts->record(
            $stockCount,
            $product,
            WarehouseLocation::query()->findOrFail($data['location_id']),
            $data['lot_code'] ?? null,
            $data['expires_at'] ?? null,
            (int) $data['quantity'],
            $data['mode'] === 'add',
            $request->user(),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __(':name: :counted sayıldı.', ['name' => $product->name, 'counted' => $line->counted])]);
    }

    public function updateLine(Request $request, StockCount $stockCount, StockCountLine $line, StockCountService $counts): RedirectResponse
    {
        abort_unless($line->stock_count_id === $stockCount->id, 404);

        $data = $request->validate(['counted' => ['required', 'integer', 'min:0', 'max:1000000']], attributes: ['counted' => __('sayılan')]);

        $counts->updateLine($line, (int) $data['counted'], $request->user());

        return back();
    }

    public function destroyLine(StockCount $stockCount, StockCountLine $line, StockCountService $counts): RedirectResponse
    {
        abort_unless($line->stock_count_id === $stockCount->id, 404);

        $counts->removeLine($line);

        return back()->with('toast', ['type' => 'success', 'message' => __('Satır silindi.')]);
    }

    public function approve(Request $request, StockCount $stockCount, StockCountService $counts): RedirectResponse
    {
        $zero = $request->boolean('zero_uncounted');

        $counts->approve($stockCount, $zero, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':number sayımı onaylandı; farklar stoğa işlendi.', ['number' => $stockCount->number])]);
    }

    public function cancel(Request $request, StockCount $stockCount, StockCountService $counts): RedirectResponse
    {
        $counts->cancel($stockCount, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':number sayımı iptal edildi.', ['number' => $stockCount->number])]);
    }

    /**
     * @return array<int, array{id: string, warehouse_id: string, code: string}>
     */
    private function locations(?string $warehouseId = null, ?string $onlyId = null): array
    {
        return WarehouseLocation::query()
            ->when(! $onlyId, fn (Builder $q) => $q->active())
            ->when($warehouseId, fn (Builder $q, string $id) => $q->where('warehouse_id', $id))
            ->when($onlyId, fn (Builder $q, string $id) => $q->whereKey($id))
            ->pickOrder()
            ->get(['id', 'warehouse_id', 'code', 'is_unassigned'])
            ->map(fn (WarehouseLocation $l) => ['id' => $l->id, 'warehouse_id' => $l->warehouse_id, 'code' => $l->label()])
            ->all();
    }
}
