<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Stok raporu: stok adedi ve değeri, tükenen / az kalan ürünler, dönemde
 * hiç satılmayan (hareketsiz) ürünler; depo ve lokasyon kırılımı.
 *
 * Yalnız stok takibi yapılan ürünler. Depo seçiliyse o deponun stoku,
 * değilse toplam stok. Stok değeri = stok × satış fiyatı (KDV hariç, TL).
 */
final class StockReport extends Report
{
    /**
     * @return array<string, string>
     */
    public static function states(): array
    {
        return [
            'in' => __('Stokta olanlar'),
            'low' => __('Kritik stoktakiler'),
            'out' => __('Tükenenler'),
            'idle' => __('Dönemde satılmayanlar'),
        ];
    }

    public function key(): string
    {
        return 'stock';
    }

    public function title(): string
    {
        return __('Stok Raporu');
    }

    public function description(): string
    {
        return __('Stok adedi ve değeri (satış fiyatı, KDV hariç, TL), kritik stok ve dönem içinde hiç satılmayan ürünler.');
    }

    public function filters(): array
    {
        return ['date', 'warehouse', 'category', 'brand', 'stock_state'];
    }

    public function summary(ReportFilters $filters): array
    {
        $qty = $this->qty($filters);
        $price = $this->priceTl();
        $t = (int) $filters->threshold;
        $limit = $this->lowLimit($filters);

        $row = $this->base($filters)
            ->selectRaw('count(*) as products')
            ->selectRaw("coalesce(sum(case when {$qty} > 0 then {$qty} else 0 end), 0) as quantity")
            ->selectRaw("coalesce(sum(case when {$qty} > 0 then {$qty} * {$price} else 0 end), 0) as value")
            ->selectRaw("sum(case when {$qty} <= 0 then 1 else 0 end) as out_count")
            ->selectRaw("sum(case when {$qty} > 0 and {$qty} <= {$limit} then 1 else 0 end) as low_count")
            ->selectRaw("sum(case when {$qty} > 0 and coalesce(sold.sold, 0) = 0 then 1 else 0 end) as idle_count")
            ->first();

        $breakdowns = [$this->byWarehouse($filters)];

        if ($filters->warehouse !== null) {
            $breakdowns[] = $this->byLocation($filters);
        }

        return [
            'kpis' => [
                $this->kpi(__('Toplam stok'), (int) $row->quantity, 'number', 'package', null, __(':count stok takipli ürün', ['count' => number_format((int) $row->products, 0, ',', '.')])),
                $this->kpi(__('Stok değeri'), Money::round($row->value), 'money', 'revenue', null, __('Satış fiyatıyla, KDV hariç')),
                $this->kpi(__('Tükenen'), (int) $row->out_count, 'number', 'packageX'),
                $this->kpi(__('Kritik stokta'), (int) $row->low_count, 'number', 'alert', null, __('Ürünün kritik stok eşiği; yoksa ≤ :t', ['t' => $t])),
                $this->kpi(__('Dönemde satılmayan'), (int) $row->idle_count, 'number', 'clock', null, __('Stokta olup hiç satılmayan')),
            ],
            'chart' => null,
            'breakdowns' => $breakdowns,
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return self::states()[$filters->stockState ?? ''] ?? __('Ürünler (stok değerine göre)');
    }

    public function columns(ReportFilters $filters): array
    {
        return [
            $this->column('name', __('Ürün')),
            $this->column('sku', __('Stok kodu')),
            $this->column('category', __('Kategori')),
            $this->column('brand', __('Marka')),
            $this->column('quantity', __('Stok'), 'number'),
            $this->column('price', __('Birim fiyat'), 'money'),
            $this->column('value', __('Stok değeri'), 'money'),
            $this->column('sold', __('Dönemde satış'), 'number'),
            $this->column('last_sale', __('Son satış'), 'date'),
        ];
    }

    protected function rows(ReportFilters $filters): Builder
    {
        $qty = $this->qty($filters);
        $price = $this->priceTl();
        $limit = $this->lowLimit($filters);

        $query = $this->base($filters)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoinSub($this->lastSales(), 'last', 'last.product_id', '=', 'products.id')
            ->select(['products.name', 'products.sku', 'categories.name as category', 'brands.name as brand', 'last.last_sale'])
            ->selectRaw("{$qty} as quantity, {$price} as price, coalesce(sold.sold, 0) as sold")
            ->selectRaw("(case when {$qty} > 0 then {$qty} * {$price} else 0 end) as value");

        match ($filters->stockState) {
            'in' => $query->whereRaw("{$qty} > 0")->orderByDesc('value'),
            'low' => $query->whereRaw("{$qty} > 0 and {$qty} <= {$limit}")->orderBy('quantity'),
            'out' => $query->whereRaw("{$qty} <= 0")->orderByDesc('last.last_sale'),
            'idle' => $query->whereRaw("{$qty} > 0 and coalesce(sold.sold, 0) = 0")->orderByDesc('value'),
            default => $query->orderByDesc('value'),
        };

        return $query->orderBy('products.name');
    }

    /**
     * "Az kalan" sınırı (SQL ifadesi): ürünün kritik stok eşiği, yoksa filtredeki eşik
     * (varsayılanı Stok Ayarları'ndaki genel eşik).
     */
    private function lowLimit(ReportFilters $filters): string
    {
        return 'coalesce(products.min_stock, '.((int) $filters->threshold).')';
    }

    protected function mapRow(object $row, ReportFilters $filters): array
    {
        return [
            'name' => $row->name,
            'sku' => $row->sku,
            'category' => $row->category,
            'brand' => $row->brand,
            'quantity' => (int) $row->quantity,
            'price' => Money::round($row->price),
            'value' => Money::round($row->value),
            'sold' => (int) $row->sold,
            'last_sale' => $row->last_sale,
        ];
    }

    /**
     * Stok takipli ürünler + seçili deponun stoku + dönem satışı.
     */
    private function base(ReportFilters $filters): Builder
    {
        $query = DB::table('products')
            ->whereNull('products.deleted_at')
            ->where('products.track_stock', true)
            ->leftJoin('currencies', 'currencies.id', '=', 'products.currency_id')
            ->leftJoinSub($this->periodSales($filters), 'sold', 'sold.product_id', '=', 'products.id');

        if ($filters->warehouse !== null) {
            $query->leftJoinSub(
                DB::table('product_stocks')
                    ->where('warehouse_id', $filters->warehouse)
                    ->selectRaw('product_id, sum(quantity) as qty')
                    ->groupBy('product_id'),
                'wh', 'wh.product_id', '=', 'products.id',
            );
        }

        if ($filters->category !== null) {
            $query->whereIn('products.category_id', $this->categoryIds($filters->category));
        }

        if ($filters->brand !== null) {
            $query->where('products.brand_id', $filters->brand);
        }

        return $query;
    }

    private function qty(ReportFilters $filters): string
    {
        return $filters->warehouse !== null ? 'coalesce(wh.qty, 0)' : 'products.stock';
    }

    /** Ürün fiyatının TL karşılığı (para biriminin güncel kuruyla). */
    private function priceTl(): string
    {
        // Güncel kurla ana para birimine: fiyat × kur(TL) / kur(ana birim, TL).
        return "(products.price * (case when currencies.code is null or currencies.code = 'TRY' then 1 else coalesce(nullif(currencies.exchange_rate, 0), 1) end) / ".Money::baseRate().')';
    }

    private function periodSales(ReportFilters $filters): Builder
    {
        return $this->valid(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id'))
            ->whereBetween('orders.created_at', [$filters->from, $filters->to])
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id, sum(order_items.quantity) as sold')
            ->groupBy('order_items.product_id');
    }

    private function lastSales(): Builder
    {
        return $this->valid(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id'))
            ->whereNotNull('order_items.product_id')
            ->selectRaw('order_items.product_id, max(orders.created_at) as last_sale')
            ->groupBy('order_items.product_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function byWarehouse(ReportFilters $filters): array
    {
        $price = $this->priceTl();
        $query = DB::table('product_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'product_stocks.warehouse_id')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'products.currency_id')
            ->whereNull('products.deleted_at')
            ->where('products.track_stock', true)
            ->where('product_stocks.quantity', '>', 0);

        if ($filters->category !== null) {
            $query->whereIn('products.category_id', $this->categoryIds($filters->category));
        }

        if ($filters->brand !== null) {
            $query->where('products.brand_id', $filters->brand);
        }

        $rows = $query
            ->selectRaw('max(warehouses.name) as label, count(distinct product_stocks.product_id) as products, sum(product_stocks.quantity) as quantity')
            ->selectRaw("sum(product_stocks.quantity * {$price}) as value")
            ->groupBy('product_stocks.warehouse_id')
            ->orderByDesc('value')
            ->get()
            ->map(fn (object $r) => [
                'label' => (string) $r->label,
                'products' => (int) $r->products,
                'quantity' => (int) $r->quantity,
                'value' => Money::round($r->value),
            ])->all();

        return $this->breakdown(__('Depolar'), [
            $this->column('label', __('Depo')),
            $this->column('products', __('Ürün çeşidi'), 'number'),
            $this->column('quantity', __('Adet'), 'number'),
            $this->column('value', __('Değer'), 'money'),
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function byLocation(ReportFilters $filters): array
    {
        $query = DB::table('location_stocks')
            ->join('warehouse_locations', 'warehouse_locations.id', '=', 'location_stocks.warehouse_location_id')
            ->join('products', 'products.id', '=', 'location_stocks.product_id')
            ->whereNull('products.deleted_at')
            ->where('location_stocks.warehouse_id', $filters->warehouse)
            ->where('location_stocks.quantity', '>', 0);

        if ($filters->category !== null) {
            $query->whereIn('products.category_id', $this->categoryIds($filters->category));
        }

        if ($filters->brand !== null) {
            $query->where('products.brand_id', $filters->brand);
        }

        $rows = $query
            ->selectRaw('max(warehouse_locations.code) as code, max(warehouse_locations.is_unassigned) as unassigned')
            ->selectRaw('count(distinct location_stocks.product_id) as products, sum(location_stocks.quantity) as quantity')
            ->groupBy('location_stocks.warehouse_location_id')
            ->orderBy('code')
            ->get()
            ->map(fn (object $r) => [
                'label' => (bool) $r->unassigned ? __('Atanmamış') : (string) $r->code,
                'products' => (int) $r->products,
                'quantity' => (int) $r->quantity,
            ])->all();

        return $this->breakdown(__('Lokasyonlar'), [
            $this->column('label', __('Lokasyon')),
            $this->column('products', __('Ürün çeşidi'), 'number'),
            $this->column('quantity', __('Adet'), 'number'),
        ], $rows);
    }
}
