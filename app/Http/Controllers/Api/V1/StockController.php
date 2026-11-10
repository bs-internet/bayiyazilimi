<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncStockRequest;
use App\Services\Api\StockSyncService;
use Illuminate\Http\JsonResponse;

/**
 * REST API toplu stok eşitleme (stock:write): mutlak miktarlar, tümü ya da hiçbiri.
 */
class StockController extends Controller
{
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
