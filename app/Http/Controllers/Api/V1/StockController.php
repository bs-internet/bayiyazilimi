<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ListsForSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncStockRequest;
use App\Models\ProductStock;
use App\Services\Api\StockSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API stok: depo bazında stok listesi (products:read) ve toplu stok
 * eşitleme (stock:write; mutlak miktarlar, tümü ya da hiçbiri).
 */
class StockController extends Controller
{
    use ListsForSync;

    public function index(Request $request): JsonResponse
    {
        $filters = $this->listFilters($request, [
            'sku' => ['nullable', 'string', 'max:64'],
            'warehouse' => ['nullable', 'string', 'max:32'],
        ]);

        $page = $this->applySync(ProductStock::query()->with(['product:id,sku,name', 'warehouse:id,code']), $filters)
            ->whereHas('product')
            ->when($filters['sku'] ?? null, fn (Builder $q, string $sku) => $q->whereHas('product', fn (Builder $p) => $p->where('sku', $sku)))
            ->when($filters['warehouse'] ?? null, fn (Builder $q, string $code) => $q->whereHas('warehouse', fn (Builder $w) => $w->where('code', $code)))
            ->paginate($this->perPage($filters))->withQueryString();

        $array = $page->toArray();

        return response()->json([
            'data' => array_map(fn (ProductStock $row) => [
                'sku' => $row->product?->sku,
                'name' => $row->product?->name,
                'warehouse' => $row->warehouse?->code,
                'quantity' => (int) $row->quantity,
                'updated_at' => $row->updated_at?->toIso8601String(),
            ], $page->items()),
            'links' => ['first' => $array['first_page_url'], 'last' => $array['last_page_url'], 'prev' => $array['prev_page_url'], 'next' => $array['next_page_url']],
            'meta' => ['current_page' => $array['current_page'], 'last_page' => $array['last_page'], 'per_page' => $array['per_page'], 'total' => $array['total']],
        ]);
    }

    public function update(SyncStockRequest $request, StockSyncService $service): JsonResponse
    {
        /** @var list<array{sku: string, warehouse: string, quantity: int}> $items */
        $items = array_values($request->validated('items'));

        $result = $service->sync($items, $request->user());

        return response()->json([
            'data' => $result,
            'meta' => [
                'count' => count($result),
                'changed' => count(array_filter($result, fn (array $row): bool => $row['changed'])),
            ],
        ]);
    }
}
