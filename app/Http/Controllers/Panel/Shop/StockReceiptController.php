<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\ReceiptDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Requests\Panel\Shop\SaveStockReceiptRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\StockReceiptService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stok › Mal Kabul: tedarikçi irsaliyesi / faturasıyla stok girişi.
 */
class StockReceiptController extends Controller
{
    use BuildsShopOptions;

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'supplier', 'warehouse']);

        $receipts = StockReceipt::query()
            ->with(['supplier:id,name', 'warehouse:id,name', 'user:id,name'])
            ->withCount('movements')
            ->withSum('movements as quantity', 'quantity')
            ->when($filters['supplier'] ?? null, fn (Builder $q, $v) => $q->where('supplier_id', $v))
            ->when($filters['warehouse'] ?? null, fn (Builder $q, $v) => $q->where('warehouse_id', $v))
            ->when($filters['q'] ?? null, fn (Builder $q, $v) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', "%{$v}%")
                ->orWhere('document_no', 'like', "%{$v}%")))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockReceipt $r) => [
                'id' => $r->id,
                'number' => $r->number,
                'supplier' => $r->supplier?->name,
                'document_type' => $r->document_type->label(),
                'document_no' => $r->document_no,
                'document_date' => $r->document_date->toDateString(),
                'warehouse' => $r->warehouse?->name,
                'lines' => (int) $r->movements_count,
                'quantity' => (int) $r->quantity,
                'user' => $r->user?->name,
                'created_at' => $r->created_at,
            ]);

        return Inertia::render('panel/shop/stock-receipts/index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'suppliers' => $this->supplierOptions(),
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/shop/stock-receipts/create', [
            'suppliers' => $this->supplierOptions(onlyActive: true),
            'warehouses' => $this->warehouseOptions(onlyActive: true),
            'defaultWarehouseId' => Warehouse::default()?->id,
            'documentTypes' => ReceiptDocumentType::options(),
            'locations' => WarehouseLocation::query()->active()->pickOrder()->get(['id', 'warehouse_id', 'code', 'is_unassigned'])
                ->map(fn (WarehouseLocation $l) => ['id' => $l->id, 'warehouse_id' => $l->warehouse_id, 'code' => $l->label()])
                ->all(),
            'maxLines' => SaveStockReceiptRequest::MAX_LINES,
        ]);
    }

    public function store(SaveStockReceiptRequest $request, StockReceiptService $receipts): RedirectResponse
    {
        $receipt = $receipts->create($request->validated(), $request->user());

        return to_route('panel.shop.stock-receipts.show', $receipt->id)
            ->with('toast', ['type' => 'success', 'message' => __(':number mal kabulü kaydedildi.', ['number' => $receipt->number])]);
    }

    public function show(StockReceipt $stockReceipt): Response
    {
        $stockReceipt->load(['supplier', 'warehouse:id,name', 'user:id,name']);

        $lines = $stockReceipt->movements()
            ->with(['product:id,sku,name,unit', 'location:id,code,is_unassigned', 'lot:id,code,expires_at'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'product_id' => $m->product_id,
                'sku' => $m->product?->sku,
                'name' => $m->product?->name,
                'unit' => $m->product?->unit,
                'location' => $m->location?->label(),
                'lot' => $m->lot?->code,
                'expires_at' => $m->lot?->expires_at?->toDateString(),
                'quantity' => $m->quantity,
            ]);

        return Inertia::render('panel/shop/stock-receipts/show', [
            'receipt' => [
                'id' => $stockReceipt->id,
                'number' => $stockReceipt->number,
                'supplier' => $stockReceipt->supplier?->only(['id', 'name', 'tax_office', 'tax_number', 'phone', 'email']),
                'document_type' => $stockReceipt->document_type->label(),
                'document_no' => $stockReceipt->document_no,
                'document_date' => $stockReceipt->document_date->toDateString(),
                'warehouse' => $stockReceipt->warehouse?->name,
                'note' => $stockReceipt->note,
                'user' => $stockReceipt->user?->name,
                'created_at' => $stockReceipt->created_at,
            ],
            'lines' => $lines,
        ]);
    }

    /**
     * Satır ekleme için ürün arama (izlenebilirlik bilgisiyle).
     */
    public function products(Request $request): JsonResponse
    {
        $items = Product::query()
            ->search($request->string('q')->toString())
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'sku', 'name', 'unit', 'tracking', 'expiry_tracking'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit,
                'tracking' => $p->trackingMode()->value,
                'expiry_tracking' => $p->tracksExpiry(),
            ])
            ->all();

        return response()->json(['data' => $items]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function supplierOptions(bool $onlyActive = false): array
    {
        return Supplier::query()
            ->when($onlyActive, fn (Builder $q) => $q->active())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Supplier $s) => ['value' => $s->id, 'label' => $s->name])
            ->all();
    }
}
