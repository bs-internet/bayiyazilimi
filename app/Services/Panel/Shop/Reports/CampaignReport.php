<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Models\Campaign;
use App\Support\Money;
use App\Support\Shop\Campaigns\CampaignRegistry;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kampanya raporu: kampanya bazında kullanım (sipariş), müşteriye sağlanan
 * kazanç ve kampanyalı siparişlerin cirosu. Satırlar kullanım kayıtlarından
 * (`campaign_usages`) okunur: birlikte uygulanan kampanyaların her biri kendi
 * satırında sayılır; silinen kampanya kayıttaki adıyla kalır.
 */
final class CampaignReport extends Report
{
    /** @var array<string, string>|null kampanya id => işlem etiketi */
    private ?array $actions = null;

    public function key(): string
    {
        return 'campaigns';
    }

    public function title(): string
    {
        return __('Kampanya Raporu');
    }

    public function description(): string
    {
        return __('Kampanya bazında kullanım, müşteriye sağlanan kazanç (indirim, hediye, kargo) ve kampanyalı siparişlerin cirosu (TL). Birlikte uygulanan kampanyalar ayrı ayrı sayılır.');
    }

    public function filters(): array
    {
        return ['date', 'customer'];
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        $withAverage = Money::div($current['revenue'], $current['orders']);
        $withoutAverage = Money::div($current['other_revenue'], $current['other_orders']);

        return [
            'kpis' => [
                $this->kpi(__('Kampanyalı sipariş'), $current['orders'], 'number', 'tag', $this->change($current['orders'], $previous['orders']),
                    __('%:rate sipariş', ['rate' => Money::number($this->percent($current['orders'], $current['orders'] + $current['other_orders']), 1)])),
                $this->kpi(__('Verilen indirim'), $current['discount'], 'money', 'discount', $this->change($current['discount'], $previous['discount'])),
                $this->kpi(__('Kampanyalı ciro'), $current['revenue'], 'money', 'revenue', $this->change($current['revenue'], $previous['revenue'])),
                $this->kpi(__('Ort. sepet (kampanyalı)'), $withAverage, 'money', 'average', null,
                    __('Kampanyasız: :amount', ['amount' => Money::format($withoutAverage)])),
            ],
            'chart' => null,
            'breakdowns' => [],
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return __('Kampanyalar (kullanıma göre)');
    }

    public function columns(ReportFilters $filters): array
    {
        return [
            $this->column('name', __('Kampanya')),
            $this->column('action', __('İşlem')),
            $this->column('orders', __('Kullanım'), 'number'),
            $this->column('customers', __('Müşteri'), 'number'),
            $this->column('benefit', __('Kazanç'), 'money'),
            $this->column('revenue', __('Ciro'), 'money'),
            $this->column('average', __('Ort. sepet'), 'money'),
            $this->column('last_used', __('Son kullanım'), 'date'),
        ];
    }

    protected function rows(ReportFilters $filters): Builder
    {
        return $this->orders($filters)
            ->join('campaign_usages', 'campaign_usages.order_id', '=', 'orders.id')
            ->selectRaw('max(campaign_usages.campaign_id) as campaign_id, max(campaign_usages.campaign_name) as name, count(*) as orders, count(distinct coalesce(orders.account_id, orders.email)) as customers')
            ->selectRaw('sum(campaign_usages.value) as benefit, sum('.$this->tl('orders.grand_total').') as revenue, max(orders.created_at) as last_used')
            ->groupBy(DB::raw('coalesce(campaign_usages.campaign_id, campaign_usages.campaign_name)'))
            ->orderByDesc('orders')
            ->orderByDesc('revenue');
    }

    protected function mapRow(object $row, ReportFilters $filters): array
    {
        $revenue = Money::round($row->revenue);

        return [
            'name' => $row->name,
            'action' => $row->campaign_id !== null ? $this->actionLabel((string) $row->campaign_id) : null,
            'orders' => (int) $row->orders,
            'customers' => (int) $row->customers,
            'benefit' => Money::round($row->benefit),
            'revenue' => $revenue,
            'average' => Money::div($revenue, (int) $row->orders),
            'last_used' => $row->last_used,
        ];
    }

    /**
     * @return array{orders: int, discount: BigDecimal, revenue: BigDecimal, other_orders: int, other_revenue: BigDecimal}
     */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->orders($filters)
            ->selectRaw('sum(case when orders.campaign_name is not null then 1 else 0 end) as orders')
            ->selectRaw('coalesce(sum(case when orders.campaign_name is not null then '.$this->tl('orders.discount_total').' else 0 end), 0) as discount')
            ->selectRaw('coalesce(sum(case when orders.campaign_name is not null then '.$this->tl('orders.grand_total').' else 0 end), 0) as revenue')
            ->selectRaw('sum(case when orders.campaign_name is null then 1 else 0 end) as other_orders')
            ->selectRaw('coalesce(sum(case when orders.campaign_name is null then '.$this->tl('orders.grand_total').' else 0 end), 0) as other_revenue')
            ->first();

        return [
            'orders' => (int) $row->orders,
            'discount' => Money::round($row->discount),
            'revenue' => Money::round($row->revenue),
            'other_orders' => (int) $row->other_orders,
            'other_revenue' => Money::round($row->other_revenue),
        ];
    }

    /** Kampanyanın işlem türü (kampanya silinmişse boş). */
    private function actionLabel(string $id): ?string
    {
        if ($this->actions === null) {
            $registry = app(CampaignRegistry::class);
            $this->actions = Campaign::query()->get(['id', 'action'])
                ->mapWithKeys(fn (Campaign $c) => [$c->id => $registry->action((string) $c->action)?->label() ?? (string) $c->action])
                ->all();
        }

        return $this->actions[$id] ?? null;
    }
}
