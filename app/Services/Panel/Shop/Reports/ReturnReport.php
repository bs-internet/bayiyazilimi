<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Enums\Shop\ReturnReason;
use App\Enums\Shop\ReturnStatus;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * İade raporu: iade talepleri, iade oranı, iade edilen tutar; neden ve
 * durum kırılımı; en çok iade edilen ürünler. Dönem, talebin tarihine göredir.
 */
final class ReturnReport extends Report
{
    public function key(): string
    {
        return 'returns';
    }

    public function title(): string
    {
        return __('İade Raporu');
    }

    public function description(): string
    {
        return __('İade talepleri, iade oranı ve iade edilen tutar (TL); nedenler ve en çok iade edilen ürünler.');
    }

    public function filters(): array
    {
        return ['date', 'customer'];
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        $orders = $this->orders($filters)->count();
        $prevOrders = $this->orders($filters->previous())->count();
        $rate = $this->percent($current['requests'], $orders);

        return [
            'kpis' => [
                $this->kpi(__('İade talebi'), $current['requests'], 'number', 'undo', $this->change($current['requests'], $previous['requests'])),
                $this->kpi(__('İade oranı'), $rate, 'percent', 'percent', $this->change($rate, $this->percent($previous['requests'], $prevOrders)), __('Talep / dönemdeki sipariş')),
                $this->kpi(__('İade edilen adet'), $current['quantity'], 'number', 'package', $this->change($current['quantity'], $previous['quantity'])),
                $this->kpi(__('İade tutarı'), $current['refund'], 'money', 'revenue', $this->change($current['refund'], $previous['refund']), __('Girilen iade tutarları')),
                $this->kpi(__('Bekleyen'), $current['pending'], 'number', 'clock'),
            ],
            'chart' => null,
            'breakdowns' => [
                $this->byEnum($filters, 'reason', __('İade nedeni'), __('Neden'), ReturnReason::cases()),
                $this->byEnum($filters, 'status', __('İade durumu'), __('Durum'), ReturnStatus::cases()),
            ],
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return __('En çok iade edilen ürünler');
    }

    public function columns(ReportFilters $filters): array
    {
        return [
            $this->column('name', __('Ürün')),
            $this->column('sku', __('Stok kodu')),
            $this->column('quantity', __('İade adedi'), 'number'),
            $this->column('requests', __('Talep'), 'number'),
            $this->column('sold', __('Dönemde satış'), 'number'),
            $this->column('rate', __('İade / satış'), 'percent'),
        ];
    }

    protected function rows(ReportFilters $filters): Builder
    {
        $sold = $this->orders($filters)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('order_items.sku, sum(order_items.quantity) as sold')
            ->groupBy('order_items.sku');

        return $this->requests($filters)
            ->join('return_request_items', 'return_request_items.return_request_id', '=', 'return_requests.id')
            ->join('order_items', 'order_items.id', '=', 'return_request_items.order_item_id')
            ->leftJoinSub($sold, 'sold', 'sold.sku', '=', 'order_items.sku')
            ->selectRaw('max(order_items.name) as name, order_items.sku as sku, sum(return_request_items.quantity) as quantity')
            ->selectRaw('count(distinct return_requests.id) as requests, max(sold.sold) as sold')
            ->groupBy('order_items.sku')
            ->orderByDesc('quantity');
    }

    protected function mapRow(object $row, ReportFilters $filters): array
    {
        $sold = (int) ($row->sold ?? 0);

        return [
            'name' => $row->name,
            'sku' => $row->sku,
            'quantity' => (int) $row->quantity,
            'requests' => (int) $row->requests,
            'sold' => $sold,
            'rate' => $sold > 0 ? $this->percent((int) $row->quantity, $sold) : null,
        ];
    }

    private function requests(ReportFilters $filters): Builder
    {
        $query = DB::table('return_requests')
            ->join('orders', 'orders.id', '=', 'return_requests.order_id')
            ->whereBetween('return_requests.created_at', [$filters->from, $filters->to]);

        return $this->applyCustomer($query, $filters, 'return_requests.account_id');
    }

    /**
     * @return array{requests: int, refund: BigDecimal, pending: int, quantity: int}
     */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->requests($filters)
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum('.$this->tl('coalesce(return_requests.refund_amount, 0)').'), 0) as refund')
            ->selectRaw("sum(case when return_requests.status = 'pending' then 1 else 0 end) as pending")
            ->first();

        $quantity = (int) $this->requests($filters)
            ->join('return_request_items', 'return_request_items.return_request_id', '=', 'return_requests.id')
            ->sum('return_request_items.quantity');

        return [
            'requests' => (int) $row->requests,
            'refund' => Money::round($row->refund),
            'pending' => (int) $row->pending,
            'quantity' => $quantity,
        ];
    }

    /**
     * @param  array<int, ReturnReason|ReturnStatus>  $cases
     * @return array<string, mixed>
     */
    private function byEnum(ReportFilters $filters, string $column, string $title, string $label, array $cases): array
    {
        $counts = $this->requests($filters)
            ->selectRaw("return_requests.{$column} as k, count(*) as requests")
            ->groupBy("return_requests.{$column}")
            ->pluck('requests', 'k');

        $rows = array_map(fn (ReturnReason|ReturnStatus $case) => [
            'label' => $case->label(),
            'requests' => (int) ($counts[$case->value] ?? 0),
        ], $cases);

        return $this->breakdown($title, [
            $this->column('label', $label),
            $this->column('requests', __('Talep'), 'number'),
        ], $rows);
    }
}
