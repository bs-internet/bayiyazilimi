<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Enums\Shop\CustomerType;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Müşteri raporu: en çok alan müşteri / bayiler, yeni ve tekrar eden
 * müşteriler, bayi grubu kırılımı. Hesabı olmayan (misafir) siparişler
 * e-posta adresine göre tek müşteri sayılır.
 */
final class CustomerReport extends Report
{
    /** Müşteri anahtarı: hesap, yoksa e-posta. */
    private const KEY = 'coalesce(orders.account_id, orders.email)';

    public function key(): string
    {
        return 'customers';
    }

    public function title(): string
    {
        return CustomerType::customersEnabled() ? __('Müşteri Raporu') : __('Bayi Raporu');
    }

    public function description(): string
    {
        // Müşteri ve misafir siparişleri yalnız vitrinle gelir.
        return CustomerType::customersEnabled()
            ? __('Müşteri ve bayi bazında sipariş ve ciro (TL). Misafir siparişler e-posta adresine göre gruplanır.')
            : __('Bayi bazında sipariş ve ciro (TL).');
    }

    public function filters(): array
    {
        return ['date', 'customer'];
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->counts($filters);
        $previous = $this->counts($filters->previous());

        $revenue = Money::round($this->orders($filters)->sum(DB::raw($this->tl('orders.grand_total'))));
        $dealerRevenue = Money::round($this->orders($filters)
            ->whereIn('orders.account_id', DB::table('accounts')->select('id')->where('type', 'dealer'))
            ->sum(DB::raw($this->tl('orders.grand_total'))));

        // B2B çekirdekte her hesap bayidir: bayi payı ve tür kırılımı anlamsız.
        $customers = CustomerType::customersEnabled();

        return [
            'kpis' => $customers ? [
                $this->kpi(__('Alışveriş yapan müşteri'), $current['active'], 'number', 'users', $this->change($current['active'], $previous['active'])),
                $this->kpi(__('Yeni müşteri'), $current['new'], 'number', 'userPlus', $this->change($current['new'], $previous['new']), __('İlk siparişi bu dönemde')),
                $this->kpi(__('Tekrar eden müşteri'), $current['returning'], 'number', 'repeat', $this->change($current['returning'], $previous['returning']), __('Daha önce de sipariş vermiş')),
                $this->kpi(__('Müşteri başına ciro'), Money::div($revenue, $current['active']), 'money', 'average'),
                $this->kpi(__('Bayi cirosu payı'), $this->percent($dealerRevenue, $revenue), 'percent', 'percent'),
            ] : [
                $this->kpi(__('Sipariş veren bayi'), $current['active'], 'number', 'users', $this->change($current['active'], $previous['active'])),
                $this->kpi(__('Yeni bayi'), $current['new'], 'number', 'userPlus', $this->change($current['new'], $previous['new']), __('İlk siparişi bu dönemde')),
                $this->kpi(__('Tekrar eden bayi'), $current['returning'], 'number', 'repeat', $this->change($current['returning'], $previous['returning']), __('Daha önce de sipariş vermiş')),
                $this->kpi(__('Bayi başına ciro'), Money::div($revenue, $current['active']), 'money', 'average'),
            ],
            'chart' => null,
            'breakdowns' => [
                ...($customers ? [$this->byType($filters)] : []),
                $this->byDealerGroup($filters),
            ],
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return __('Müşteriler (ciroya göre)');
    }

    public function columns(ReportFilters $filters): array
    {
        return [
            $this->column('name', __('Müşteri')),
            $this->column('email', __('E-posta')),
            $this->column('type', __('Tip')),
            $this->column('dealer_group', __('Bayi grubu')),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('revenue', __('Ciro'), 'money'),
            $this->column('average', __('Ort. sepet'), 'money'),
            $this->column('last_order', __('Son sipariş'), 'date'),
        ];
    }

    protected function rows(ReportFilters $filters): Builder
    {
        return $this->orders($filters)
            ->leftJoin('accounts', 'accounts.id', '=', 'orders.account_id')
            ->leftJoin('dealer_groups', 'dealer_groups.id', '=', 'accounts.dealer_group_id')
            ->selectRaw('max(coalesce(accounts.title, orders.customer_name)) as name, max(orders.email) as email')
            ->selectRaw("max(coalesce(accounts.type, 'guest')) as type, max(dealer_groups.name) as dealer_group")
            ->selectRaw('count(*) as orders, sum('.$this->tl('orders.grand_total').') as revenue, max(orders.created_at) as last_order')
            ->groupBy(DB::raw(self::KEY))
            ->orderByDesc('revenue');
    }

    protected function mapRow(object $row, ReportFilters $filters): array
    {
        $revenue = Money::round($row->revenue);

        return [
            'name' => $row->name,
            'email' => $row->email,
            'type' => ReportFilters::customerTypeLabels()[$row->type] ?? $row->type,
            'dealer_group' => $row->dealer_group,
            'orders' => (int) $row->orders,
            'revenue' => $revenue,
            'average' => Money::div($revenue, (int) $row->orders),
            'last_order' => $row->last_order,
        ];
    }

    /**
     * @return array{active: int, new: int, returning: int}
     */
    private function counts(ReportFilters $filters): array
    {
        $active = (int) DB::query()
            ->fromSub($this->orders($filters)->selectRaw(self::KEY.' as customer')->groupBy(DB::raw(self::KEY)), 'c')
            ->count();

        // İlk geçerli siparişi bu dönemde olanlar.
        $firstOrders = $this->applyCustomer($this->valid(DB::table('orders')), $filters, 'orders.account_id')
            ->selectRaw(self::KEY.' as customer, min(orders.created_at) as first_at')
            ->groupBy(DB::raw(self::KEY));

        $new = (int) DB::query()
            ->fromSub($firstOrders, 'f')
            ->whereBetween('f.first_at', [$filters->from, $filters->to])
            ->count();

        return ['active' => $active, 'new' => $new, 'returning' => max(0, $active - $new)];
    }

    /**
     * @return array<string, mixed>
     */
    private function byType(ReportFilters $filters): array
    {
        $rows = $this->orders($filters)
            ->leftJoin('accounts', 'accounts.id', '=', 'orders.account_id')
            ->selectRaw("coalesce(accounts.type, 'guest') as k, count(distinct ".self::KEY.') as customers, count(*) as orders, sum('.$this->tl('orders.grand_total').') as revenue')
            ->groupBy(DB::raw("coalesce(accounts.type, 'guest')"))
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $r) => [
                'label' => ReportFilters::customerTypeLabels()[$r->k] ?? (string) $r->k,
                'customers' => (int) $r->customers,
                'orders' => (int) $r->orders,
                'revenue' => Money::round($r->revenue),
            ])->all();

        return $this->breakdown(__('Müşteri tipi'), [
            $this->column('label', __('Tip')),
            $this->column('customers', __('Müşteri'), 'number'),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('revenue', __('Ciro'), 'money'),
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function byDealerGroup(ReportFilters $filters): array
    {
        $rows = $this->orders($filters)
            ->join('accounts', 'accounts.id', '=', 'orders.account_id')
            ->leftJoin('dealer_groups', 'dealer_groups.id', '=', 'accounts.dealer_group_id')
            ->where('accounts.type', 'dealer')
            ->selectRaw("coalesce(max(dealer_groups.name), 'Grupsuz') as label, count(distinct orders.account_id) as customers, count(*) as orders, sum(".$this->tl('orders.grand_total').') as revenue')
            ->groupBy('accounts.dealer_group_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $r) => [
                'label' => (string) $r->label,
                'customers' => (int) $r->customers,
                'orders' => (int) $r->orders,
                'revenue' => Money::round($r->revenue),
            ])->all();

        return $this->breakdown(__('Bayi grupları'), [
            $this->column('label', __('Grup')),
            $this->column('customers', __('Bayi'), 'number'),
            $this->column('orders', __('Sipariş'), 'number'),
            $this->column('revenue', __('Ciro'), 'money'),
        ], $rows);
    }
}
