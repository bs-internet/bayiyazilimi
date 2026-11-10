<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Enums\Shop\QuoteStatus;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Teklif raporu: gelen, onaylanan, reddedilen teklifler; siparişe dönüşme
 * oranı ve ortalama yanıt süresi. Dönem, teklifin geliş tarihine göredir.
 */
final class QuoteReport extends Report
{
    public function key(): string
    {
        return 'quotes';
    }

    public function title(): string
    {
        return __('Teklif Raporu');
    }

    public function description(): string
    {
        return __('Dönemde açılan teklifler (müşteri talepleri ve yönetici teklifleri), müşteri onayı ve siparişe dönüşme oranları, fiyatlandırma süresi. Tutarlar TL.');
    }

    public function filters(): array
    {
        return ['date', 'customer'];
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        return [
            'kpis' => [
                $this->kpi(__('Teklif'), $current['total'], 'number', 'fileText', $this->change($current['total'], $previous['total'])),
                $this->kpi(__('Onaylanan'), $current['approved'], 'number', 'check', $this->change($current['approved'], $previous['approved']), __('Onay oranı %:rate', ['rate' => $this->trPercent($this->percent($current['approved'], $current['total']))])),
                $this->kpi(__('Siparişe dönüşen'), $current['ordered'], 'number', 'orders', $this->change($current['ordered'], $previous['ordered']), __('Dönüşüm oranı %:rate', ['rate' => $this->trPercent($this->percent($current['ordered'], $current['total']))])),
                $this->kpi(__('Onaylanan tutar'), $current['approved_amount'], 'money', 'revenue', $this->change($current['approved_amount'], $previous['approved_amount'])),
                $this->kpi(__('Ort. fiyatlandırma süresi (saat)'), $current['response_hours'], 'number', 'clock', null, __('Talepten müşteriye gönderime')),
            ],
            'chart' => [
                'type' => 'bar',
                'title' => $this->monthly($filters) ? __('Aylık teklifler') : __('Günlük teklifler'),
                'series' => [
                    ['key' => 'total', 'label' => __('Gelen'), 'type' => 'number'],
                    ['key' => 'approved', 'label' => __('Onaylanan'), 'type' => 'number'],
                ],
                'data' => $this->buckets($filters, $this->quotes($filters)
                    ->selectRaw('date(quotes.created_at) as day, count(*) as total')
                    ->selectRaw("sum(case when quotes.status in ('approved', 'ordered') then 1 else 0 end) as approved")
                    ->groupBy(DB::raw('date(quotes.created_at)'))
                    ->get()
                    ->mapWithKeys(fn (object $r) => [(string) $r->day => ['total' => (int) $r->total, 'approved' => (int) $r->approved]]),
                    ['total', 'approved']),
            ],
            'breakdowns' => [$this->byStatus($filters)],
        ];
    }

    public function tableTitle(ReportFilters $filters): string
    {
        return __('Dönemdeki teklifler');
    }

    public function columns(ReportFilters $filters): array
    {
        return [
            $this->column('number', __('Teklif no')),
            $this->column('created_at', __('Tarih'), 'datetime'),
            $this->column('customer', __('Müşteri')),
            $this->column('status', __('Durum')),
            $this->column('amount', __('Tutar'), 'money'),
            $this->column('source', __('Kaynak')),
            $this->column('response_hours', __('Fiyatlama (saat)'), 'number'),
            $this->column('order', __('Sipariş no')),
        ];
    }

    protected function rows(ReportFilters $filters): Builder
    {
        return $this->quotes($filters)
            ->leftJoin('orders', 'orders.id', '=', 'quotes.order_id')
            ->select(['quotes.number', 'quotes.created_at', 'quotes.sent_at', 'quotes.customer_name', 'quotes.company', 'quotes.status', 'quotes.source', 'orders.number as order_number'])
            ->selectRaw($this->tl('quotes.grand_total', 'quotes').' as amount')
            ->orderByDesc('quotes.created_at');
    }

    protected function mapRow(object $row, ReportFilters $filters): array
    {
        return [
            'number' => $row->number,
            'created_at' => $row->created_at,
            'customer' => $row->company ? __(':company (:customer_name)', ['company' => $row->company, 'customer_name' => $row->customer_name]) : $row->customer_name,
            'status' => QuoteStatus::tryFrom((string) $row->status)?->label() ?? $row->status,
            'amount' => Money::round($row->amount),
            'source' => $row->source === 'admin' ? __('Yönetici') : __('Müşteri talebi'),
            'response_hours' => $row->source === 'request' ? $this->hours($row->created_at, $row->sent_at) : null,
            'order' => $row->order_number,
        ];
    }

    private function quotes(ReportFilters $filters): Builder
    {
        $query = DB::table('quotes')
            ->where('quotes.status', '!=', QuoteStatus::Draft->value)
            ->whereBetween('quotes.created_at', [$filters->from, $filters->to]);

        return $this->applyCustomer($query, $filters, 'quotes.account_id');
    }

    /**
     * @return array{total: int, approved: int, ordered: int, approved_amount: BigDecimal, response_hours: float}
     */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->quotes($filters)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when quotes.status in ('approved', 'ordered') then 1 else 0 end) as approved")
            ->selectRaw('sum(case when quotes.order_id is not null then 1 else 0 end) as ordered')
            ->selectRaw("coalesce(sum(case when quotes.status in ('approved', 'ordered') then ".$this->tl('quotes.grand_total', 'quotes').' else 0 end), 0) as approved_amount')
            ->first();

        // Yanıt süresi PHP'de (veritabanından bağımsız tarih farkı).
        $hours = $this->quotes($filters)
            ->whereNotNull('quotes.sent_at')
            ->where('quotes.source', 'request')
            ->get(['quotes.created_at', 'quotes.sent_at'])
            ->map(fn (object $r) => $this->hours($r->created_at, $r->sent_at))
            ->filter(fn (?float $h) => $h !== null);

        return [
            'total' => (int) $row->total,
            'approved' => (int) $row->approved,
            'ordered' => (int) $row->ordered,
            'approved_amount' => Money::round($row->approved_amount),
            'response_hours' => $hours->isEmpty() ? 0 : round((float) $hours->avg(), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function byStatus(ReportFilters $filters): array
    {
        $counts = $this->quotes($filters)
            ->selectRaw('quotes.status as k, count(*) as quotes, sum('.$this->tl('quotes.grand_total', 'quotes').') as amount')
            ->groupBy('quotes.status')
            ->get()
            ->keyBy('k');

        $rows = array_map(fn (QuoteStatus $status) => [
            'label' => $status->label(),
            'quotes' => (int) ($counts->get($status->value)->quotes ?? 0),
            'amount' => Money::round($counts->get($status->value)->amount ?? 0),
        ], array_values(array_filter(QuoteStatus::cases(), fn (QuoteStatus $s) => $s !== QuoteStatus::Draft)));

        return $this->breakdown(__('Teklif durumu'), [
            $this->column('label', __('Durum')),
            $this->column('quotes', __('Teklif'), 'number'),
            $this->column('amount', __('Tutar'), 'money'),
        ], $rows);
    }

    private function hours(mixed $from, mixed $to): ?float
    {
        if ($from === null || $to === null) {
            return null;
        }

        return round(CarbonImmutable::parse($from)->diffInMinutes(CarbonImmutable::parse($to)) / 60, 1);
    }

    private function trPercent(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }
}
