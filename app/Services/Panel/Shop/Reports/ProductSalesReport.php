<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;

/**
 * Ürün satışları: ürün, kategori ya da marka bazında satılan adet ve tutar.
 * Tutar sipariş satırının KDV dahil toplamıdır (kampanya indirimi düşülmüş).
 */
final class ProductSalesReport extends Report
{
    /**
     * @return array<string, string>
     */
    private static function groups(): array
    {
        return [
            'product' => __('Ürün'),
            'category' => __('Kategori'),
            'brand' => __('Marka'),
        ];
    }

    /** Dönemin toplam satış tutarı (pay % için; bir istekte bir kez). */
    private ?BigDecimal $total = null;

    public function key(): string
    {
        return 'products';
    }

    public function title(): string
    {
        return __('Ürün Satışları');
    }

    public function description(): string
    {
        return __('Ürün, kategori ya da marka bazında satılan adet ve tutar (TL, KDV dahil, kampanya indirimi düşülmüş).');
    }

    public function filters(): array
    {
        return ['date', 'customer', 'group_by', 'category', 'brand'];
    }

    public function groupByOptions(): array
    {
        return self::groups();
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        return [
            'kpis' => [
                $this->kpi(__('Satılan adet'), $current['quantity'], 'number', 'package', $this->change($current['quantity'], $previous['quantity'])),
                $this->kpi(__('Satış tutarı'), $current['revenue'], 'money', 'revenue', $this->change($current['revenue'], $previous['revenue'])),
                $this->kpi(__('Satılan ürün çeşidi'), $current['products'], 'number', 'boxes', $this->change($current['products'], $previous['products'])),
                $this->kpi(__('Sipariş başına adet'), $current['orders'] > 0 ? round($current['quantity'] / $current['orders'], 1) : 0, 'number', 'orders'),
            ],
            'chart' => null,
            'breakdowns' => [],
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return match ($this->group($filters)) {
            'category' => __('Kategori bazında satışlar'),
            'brand' => __('Marka bazında satışlar'),
            default => __('Ürün bazında satışlar'),
        };
    }

    public function columns(ReportFilters $filters): array
    {
        $tail = [
            $this->column('quantity', __('Adet'), 'number'),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('revenue', __('Tutar'), 'money'),
            $this->column('share', __('Pay'), 'percent'),
        ];

        return match ($this->group($filters)) {
            'category' => [$this->column('name', __('Kategori')), $this->column('products', __('Ürün çeşidi'), 'number'), ...$tail],
            'brand' => [$this->column('name', __('Marka')), $this->column('products', __('Ürün çeşidi'), 'number'), ...$tail],
            default => [
                $this->column('name', __('Ürün')),
                $this->column('sku', __('Stok kodu')),
                $this->column('category', __('Kategori')),
                $this->column('brand', __('Marka')),
                ...$tail,
            ],
        };
    }

    protected function rows(ReportFilters $filters): Builder
    {
        $line = $this->tl('order_items.line_total');
        $query = $this->items($filters)
            ->selectRaw("sum(order_items.quantity) as quantity, count(distinct order_items.order_id) as orders, sum({$line}) as revenue");

        return match ($this->group($filters)) {
            'category' => $query
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->selectRaw("coalesce(max(categories.name), 'Kategorisiz') as name, count(distinct order_items.sku) as products")
                ->groupBy('products.category_id')
                ->orderByDesc('revenue'),
            'brand' => $query
                ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                ->selectRaw("coalesce(max(brands.name), 'Markasız') as name, count(distinct order_items.sku) as products")
                ->groupBy('products.brand_id')
                ->orderByDesc('revenue'),
            default => $query
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                ->selectRaw('max(order_items.name) as name, order_items.sku as sku, max(categories.name) as category, max(brands.name) as brand')
                ->groupBy('order_items.sku')
                ->orderByDesc('revenue'),
        };
    }

    protected function mapRow(object $row, ReportFilters $filters): array
    {
        $this->total ??= $this->totals($filters)['revenue'];
        $revenue = Money::round($row->revenue);

        return [
            'name' => $row->name,
            'sku' => $row->sku ?? null,
            'category' => $row->category ?? null,
            'brand' => $row->brand ?? null,
            'products' => isset($row->products) ? (int) $row->products : null,
            'quantity' => (int) $row->quantity,
            'orders' => (int) $row->orders,
            'revenue' => $revenue,
            'share' => $this->percent($revenue, $this->total),
        ];
    }

    /**
     * Dönemdeki ciroya sayılan siparişlerin satırları (ürün / kategori / marka süzgeçli).
     */
    private function items(ReportFilters $filters): Builder
    {
        $query = $this->orders($filters)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id');

        if ($filters->category !== null) {
            $query->whereIn('products.category_id', $this->categoryIds($filters->category));
        }

        if ($filters->brand !== null) {
            $query->where('products.brand_id', $filters->brand);
        }

        return $query;
    }

    /**
     * @return array{quantity: int, revenue: BigDecimal, products: int, orders: int}
     */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->items($filters)
            ->selectRaw('coalesce(sum(order_items.quantity), 0) as quantity')
            ->selectRaw('coalesce(sum('.$this->tl('order_items.line_total').'), 0) as revenue')
            ->selectRaw('count(distinct order_items.sku) as products')
            ->selectRaw('count(distinct orders.id) as orders')
            ->first();

        return [
            'quantity' => (int) $row->quantity,
            'revenue' => Money::round($row->revenue),
            'products' => (int) $row->products,
            'orders' => (int) $row->orders,
        ];
    }

    private function group(ReportFilters $filters): string
    {
        return array_key_exists((string) $filters->groupBy, self::groups()) ? (string) $filters->groupBy : 'product';
    }
}
