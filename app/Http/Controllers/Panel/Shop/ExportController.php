<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shop\ProductExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * Ürünleri (ürün listesindeki filtrelerle) içe aktarılabilir Excel biçiminde indirir.
     */
    public function products(Request $request, ProductExporter $exporter): BinaryFileResponse
    {
        $filters = $request->only(['q', 'category', 'brand', 'status', 'sale_mode', 'stock']);

        $query = Product::query()
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['brand'] ?? null, fn ($q, $v) => $q->where('brand_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['sale_mode'] ?? null, fn ($q, $v) => $q->where('sale_mode', $v))
            ->when(($filters['stock'] ?? null) === 'in', fn ($q) => $q->where('stock', '>', 0))
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->where('stock', '<=', 0));

        $path = $exporter->export($query);

        CatalogChangedEvent::dispatch(null, 'product', 'exported', __('ürünleri Excel\'e aktardı.'), $request->user());

        return response()->download($path, 'urunler-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
