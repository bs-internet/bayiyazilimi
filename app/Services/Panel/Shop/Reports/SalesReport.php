<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Support\Money;
use App\Support\Payments\PaymentMethods;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satış raporu: ciro, sipariş, ortalama sepet; dönem grafiği; ödeme yöntemi,
 * durum, müşteri tipi ve para birimi kırılımları; dönem tablosu.
 */
final class SalesReport extends Report
{
    public function key(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return __('Satış Raporu');
    }

    public function description(): string
    {
        return __('Ciro, sipariş sayısı ve ortalama sepet. Tutarlar TL, KDV ve kargo dahil; iptal / iade edilen siparişler sayılmaz.');
    }

    public function filters(): array
    {
        return ['date', 'customer'];
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($this->orders($filters));
        $previous = $this->totals($this->orders($filters->previous()));

        $average = Money::div($current['revenue'], $current['orders']);
        $prevAverage = Money::div($previous['revenue'], $previous['orders']);

        return [
            'kpis' => [
                $this->kpi(__('Ciro'), $current['revenue'], 'money', 'revenue', $this->change($current['revenue'], $previous['revenue'])),
                $this->kpi(__('Sipariş'), $current['orders'], 'number', 'orders', $this->change($current['orders'], $previous['orders'])),
                $this->kpi(__('Ortalama sepet'), $average, 'money', 'average', $this->change($average, $prevAverage)),
                $this->kpi(__('Kampanya indirimi'), $current['discount'], 'money', 'discount', $this->change($current['discount'], $previous['discount'])),
                $this->kpi(__('KDV'), $current['tax'], 'money', 'receipt', $this->change($current['tax'], $previous['tax'])),
            ],
            'chart' => [
                'type' => 'area',
                'title' => $this->monthly($filters) ? __('Aylık ciro') : __('Günlük ciro'),
                'series' => [['key' => 'revenue', 'label' => __('Ciro'), 'type' => 'money']],
                'data' => $this->periodRows($filters),
            ],
            'breakdowns' => [
                $this->byPaymentMethod($filters),
                $this->byStatus($filters),
                // Müşteri / bayi kırılımı yalnız müşteri hesapları varken (vitrin).
                ...(CustomerType::customersEnabled() ? [$this->byCustomerType($filters)] : []),
                $this->byCurrency($filters),
            ],
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return $this->monthly($filters) ? __('Aylık satışlar') : __('Günlük satışlar');
    }

    public function columns(ReportFilters $filters): array
    {
        return [
            $this->column('label', __('Dönem')),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('subtotal', __('Ara toplam'), 'money'),
            $this->column('discount', __('İndirim'), 'money'),
            $this->column('tax', __('KDV'), 'money'),
            $this->column('shipping', __('Kargo'), 'money'),
            $this->column('revenue', __('Ciro'), 'money'),
        ];
    }

    protected function rows(ReportFilters $filters): array
    {
        // En yeni dönem üstte.
        return array_reverse($this->periodRows($filters));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function periodRows(ReportFilters $filters): array
    {
        $daily = $this->orders($filters)
            ->selectRaw('date(orders.created_at) as day, count(*) as orders')
            ->selectRaw('sum('.$this->tl('orders.subtotal').') as subtotal')
            ->selectRaw('sum('.$this->tl('orders.discount_total').') as discount')
            ->selectRaw('sum('.$this->tl('orders.tax_total').') as tax')
            ->selectRaw('sum('.$this->tl('orders.shipping_total').') as shipping')
            ->selectRaw('sum('.$this->tl('orders.grand_total').') as revenue')
            ->groupBy(DB::raw('date(orders.created_at)'))
            ->get()
            ->mapWithKeys(fn (object $r) => [(string) $r->day => [
                'orders' => (int) $r->orders,
                'subtotal' => Money::round($r->subtotal),
                'discount' => Money::round($r->discount),
                'tax' => Money::round($r->tax),
                'shipping' => Money::round($r->shipping),
                'revenue' => Money::round($r->revenue),
            ]]);

        return $this->buckets($filters, $daily, ['orders', 'subtotal', 'discount', 'tax', 'shipping', 'revenue'], ['subtotal', 'discount', 'tax', 'shipping', 'revenue']);
    }

    /**
     * @return array{orders: int, revenue: BigDecimal, discount: BigDecimal, shipping: BigDecimal, tax: BigDecimal}
     */
    private function totals(Builder $query): array
    {
        $row = $query
            ->selectRaw('count(*) as orders')
            ->selectRaw('coalesce(sum('.$this->tl('orders.grand_total').'), 0) as revenue')
            ->selectRaw('coalesce(sum('.$this->tl('orders.discount_total').'), 0) as discount')
            ->selectRaw('coalesce(sum('.$this->tl('orders.shipping_total').'), 0) as shipping')
            ->selectRaw('coalesce(sum('.$this->tl('orders.tax_total').'), 0) as tax')
            ->first();

        return [
            'orders' => (int) $row->orders,
            'revenue' => Money::round($row->revenue),
            'discount' => Money::round($row->discount),
            'shipping' => Money::round($row->shipping),
            'tax' => Money::round($row->tax),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function byPaymentMethod(ReportFilters $filters): array
    {
        $rows = $this->orders($filters)
            ->selectRaw('orders.payment_method as k, count(*) as orders, sum('.$this->tl('orders.grand_total').') as revenue')
            ->groupBy('orders.payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $r) => [
                'label' => PaymentMethods::label((string) $r->k),
                'orders' => (int) $r->orders,
                'revenue' => Money::round($r->revenue),
            ])->all();

        return $this->breakdown(__('Ödeme yöntemi'), $this->countRevenueColumns(__('Yöntem')), $rows);
    }

    /**
     * Durum kırılımı iptal / başarısız siparişleri de gösterir (ciroya sayılmasalar da).
     *
     * @return array<string, mixed>
     */
    private function byStatus(ReportFilters $filters): array
    {
        $counts = $this->orders($filters, valid: false)
            ->selectRaw('orders.status as k, count(*) as orders, sum('.$this->tl('orders.grand_total').') as revenue')
            ->groupBy('orders.status')
            ->get()
            ->keyBy('k');

        $rows = [];
        foreach (OrderStatus::cases() as $status) {
            $row = $counts->get($status->value);
            $rows[] = [
                'label' => $status->label(),
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => Money::round($row->revenue ?? 0),
            ];
        }

        return $this->breakdown(__('Sipariş durumu (tümü)'), $this->countRevenueColumns(__('Durum'), __('Tutar')), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function byCustomerType(ReportFilters $filters): array
    {
        $rows = $this->orders($filters)
            ->leftJoin('accounts', 'accounts.id', '=', 'orders.account_id')
            ->selectRaw("coalesce(accounts.type, 'guest') as k, count(*) as orders, sum(".$this->tl('orders.grand_total').') as revenue')
            ->groupBy(DB::raw("coalesce(accounts.type, 'guest')"))
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $r) => [
                'label' => ReportFilters::customerTypeLabels()[$r->k] ?? (string) $r->k,
                'orders' => (int) $r->orders,
                'revenue' => Money::round($r->revenue),
            ])->all();

        return $this->breakdown(__('Müşteri tipi'), $this->countRevenueColumns(__('Tip')), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function byCurrency(ReportFilters $filters): array
    {
        $rows = $this->orders($filters)
            ->selectRaw('orders.currency_code as k, count(*) as orders, sum(orders.grand_total) as original, sum('.$this->tl('orders.grand_total').') as revenue')
            ->groupBy('orders.currency_code')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $r) => [
                'label' => (string) $r->k,
                'orders' => (int) $r->orders,
                'original' => Money::number($r->original).' '.$r->k,
                'revenue' => Money::round($r->revenue),
            ])->all();

        return $this->breakdown(__('Para birimi'), [
            $this->column('label', __('Para birimi')),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('original', __('Tutar')),
            $this->column('revenue', __('TL karşılığı'), 'money'),
        ], $rows);
    }

    /**
     * @return array<int, array{key: string, label: string, type: string}>
     */
    private function countRevenueColumns(string $label, ?string $revenue = null): array
    {
        $revenue ??= __('Ciro');

        return [
            $this->column('label', $label),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('revenue', $revenue, 'money'),
        ];
    }
}
