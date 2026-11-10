<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\LocationStock;
use App\Models\Warehouse;
use App\Support\SpreadsheetRow;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Tek bir deponun stoklarını Excel'e yazar (depo sayımı / kontrol listesi).
 * Her satır bir ürün + lokasyondur; toplama sırasıyla (koridor/raf/göz,
 * "Atanmamış" en sonda) dizilir. Yalnızca miktarı sıfırdan farklılar yazılır.
 * "Kritik Stok" ürünün eşiğidir (boşsa Stok Ayarları'ndaki genel eşik).
 */
class WarehouseStockExporter
{
    public function export(Warehouse $warehouse): string
    {
        @set_time_limit(0);

        $path = storage_path('app/private/depo-'.$warehouse->id.'-'.now()->format('Ymd-His').'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(SpreadsheetRow::of(
            [__('Lokasyon'), 'SKU', __('Ürün'), __('Marka'), __('Kategori'), __('Barkod'), __('Lot / Seri No'), 'SKT', __('Birim'), __('Miktar'), __('Kritik Stok')],
            (new Style)->withFontBold(true),
        ));

        LocationStock::query()
            ->where('location_stocks.warehouse_id', $warehouse->id)
            ->where('location_stocks.quantity', '!=', 0)
            ->join('warehouse_locations', 'warehouse_locations.id', '=', 'location_stocks.warehouse_location_id')
            ->join('products', 'products.id', '=', 'location_stocks.product_id')
            ->with([
                'location:id,code,is_unassigned',
                'lot:id,code,expires_at',
                'product:id,sku,name,barcode,unit,brand_id,category_id,min_stock',
                'product.brand:id,name',
                'product.category:id,name',
            ])
            ->orderBy('warehouse_locations.is_unassigned')
            ->orderBy('warehouse_locations.sort_order')
            ->orderBy('warehouse_locations.code')
            ->orderBy('products.sku')
            ->select('location_stocks.*')
            ->chunk(500, function ($stocks) use ($writer) {
                foreach ($stocks as $stock) {
                    /** @var LocationStock $stock */
                    $p = $stock->product;

                    $writer->addRow(SpreadsheetRow::of([
                        $stock->location?->label(),
                        $p?->sku,
                        $p?->name,
                        $p?->brand?->name,
                        $p?->category?->name,
                        $p?->barcode,
                        $stock->lot?->code,
                        $stock->lot?->expires_at?->format('d.m.Y'),
                        $p?->unit,
                        (int) $stock->quantity,
                        $p?->lowStockThreshold(),
                    ]));
                }
            });

        $writer->close();

        return $path;
    }
}
