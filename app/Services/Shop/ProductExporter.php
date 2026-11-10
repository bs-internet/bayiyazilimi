<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\SpreadsheetRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ürünleri ürün Excel şablonuyla (ProductSheet) yazar; aynı dosya düzenlenip
 * geri yüklenebilir (gidiş-dönüş). Boş şablon da buradan üretilir.
 */
class ProductExporter
{
    /**
     * @param  Builder<Product>|null  $query  filtrelenmiş sorgu (yoksa tüm ürünler)
     */
    public function export(?Builder $query = null): string
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $warehouses = Warehouse::query()->ordered()->get(['id', 'code']);
        $categoryPaths = $this->categoryPaths();

        return $this->write('urunler', $warehouses, function (Writer $writer) use ($query, $warehouses, $categoryPaths) {
            ($query ?? Product::query())
                ->with(['brand:id,name', 'currency:id,code', 'stocks:id,product_id,warehouse_id,quantity', 'images'])
                ->orderBy('sku')
                ->chunk(500, function ($products) use ($writer, $warehouses, $categoryPaths) {
                    foreach ($products as $product) {
                        $writer->addRow(SpreadsheetRow::of($this->row($product, $warehouses, $categoryPaths)));
                    }
                });
        });
    }

    /**
     * Boş şablon: başlık satırı (mevcut depoların stok sütunlarıyla) + Açıklamalar sayfası.
     */
    public function template(): string
    {
        return $this->write('urun-sablonu', Warehouse::query()->ordered()->get(['id', 'code']), fn () => null);
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  callable(Writer): void  $rows
     */
    private function write(string $name, Collection $warehouses, callable $rows): string
    {
        $path = storage_path('app/private/'.$name.'-'.now()->format('Ymd-His').'-'.uniqid().'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);

        ProductSheet::writeHeader($writer, ProductSheet::headers($warehouses));
        $rows($writer);
        ProductSheet::writeHelpSheet($writer, $warehouses);

        $writer->close();

        return $path;
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  array<string, string>  $categoryPaths
     * @return list<mixed>
     */
    private function row(Product $p, Collection $warehouses, array $categoryPaths): array
    {
        $yesNo = fn (bool $value) => $value ? 'Evet' : 'Hayır';
        $highlights = array_values($p->highlights ?? []);
        $images = $p->images->map(fn (Media $m) => $m->url() !== null ? url($m->url()) : null)->filter()->values()->all();
        $stocks = $p->stocks->keyBy('warehouse_id');

        $values = [
            'sku' => $p->sku,
            'name' => $p->name,
            'brand' => $p->brand?->name,
            'category' => $p->category_id ? ($categoryPaths[$p->category_id] ?? null) : null,
            'model_number' => $p->model_number,
            'barcode' => $p->barcode,
            // Excel hücresi sayı ister (yalnız sunum; içe aktarımda yeniden kesin okunur).
            'price' => Money::toFloat($p->price),
            'compare_price' => $p->compare_price !== null ? Money::toFloat($p->compare_price) : null,
            'currency' => $p->currency?->code,
            'tax_rate' => Money::toFloat($p->tax_rate),
            'sale_mode' => $p->sale_mode === SaleMode::Quote ? 'Teklif' : 'Sepet',
            'status' => $p->status === Status::Active ? 'Aktif' : 'Pasif',
            'is_new' => $yesNo((bool) $p->is_new),
            'free_shipping' => $yesNo((bool) $p->free_shipping),
            'unit' => $p->unit,
            'min_qty' => $p->min_qty,
            'max_qty' => $p->max_qty,
            'delivery_days' => $p->delivery_days,
            'min_stock' => $p->min_stock,
            'desi' => Money::toFloat($p->desi),
            'highlight_1' => $highlights[0] ?? null,
            'highlight_2' => $highlights[1] ?? null,
            'highlight_3' => $highlights[2] ?? null,
            'notice' => $p->notice,
            'description' => $p->description,
            'slug' => $p->slug,
            'meta_title' => $p->meta_title,
            'meta_description' => $p->meta_description,
            'search_keywords' => $p->search_keywords,
            'image_1' => $images[0] ?? null,
            'image_2' => $images[1] ?? null,
            'image_3' => $images[2] ?? null,
            'image_4' => $images[3] ?? null,
            'image_5' => $images[4] ?? null,
        ];

        // Sütun sırası şablondaki sırayla aynı olmalı.
        $row = array_map(fn (string $field) => $values[$field], array_keys(ProductSheet::COLUMNS));

        foreach ($warehouses as $warehouse) {
            $row[] = (int) ($stocks->get($warehouse->id)?->quantity ?? 0);
        }

        return $row;
    }

    /**
     * @return array<string, string> kategori id => "Üst > Alt" yolu
     */
    private function categoryPaths(): array
    {
        $all = Category::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $paths = [];

        foreach ($all as $category) {
            $names = [$category->name];
            $current = $category;
            $guard = 0;

            while ($current->parent_id !== null && $all->has($current->parent_id) && $guard++ < 10) {
                $current = $all[$current->parent_id];
                array_unshift($names, $current->name);
            }

            $paths[$category->id] = implode(' > ', $names);
        }

        return $paths;
    }
}
