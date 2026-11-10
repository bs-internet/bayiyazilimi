<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Support\Money;
use App\Support\Pagination;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bir rapor: özet (KPI kutuları, grafik, kırılım tabloları) ve ana tablo.
 * Ekran ana tabloyu sayfalı gösterir; Excel aynı tabloyu tümüyle yazar.
 *
 * Tutarlar ana para birimine çevrilir (siparişteki kurla). Ciro tanımı başlangıç
 * ekranıyla aynıdır: iptal / başarısız ve ödemesi iade edilmiş siparişler
 * sayılmaz.
 *
 * Sütun türleri: text, number, money, percent, date, datetime.
 */
abstract class Report
{
    /** Ekrandaki tablo satır sayısı. */
    public const PER_PAGE = Pagination::PER_PAGE;

    /** Rota / yetki anahtarı (panel.shop.reports.{key}). */
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Ekranda gösterilecek filtreler: date, customer, warehouse, category,
     * brand, group_by, stock_state.
     *
     * @return array<int, string>
     */
    abstract public function filters(): array;

    /**
     * @return array{kpis: array<int, array<string, mixed>>, chart: array<string, mixed>|null, breakdowns: array<int, array<string, mixed>>}
     */
    abstract public function summary(ReportFilters $filters): array;

    /**
     * @return array<int, array{key: string, label: string, type: string}>
     */
    abstract public function columns(ReportFilters $filters): array;

    /**
     * Ana tablonun satırları: sıralı sorgu (sayfalanır) ya da hazır dizi.
     *
     * @return Builder|array<int, array<string, mixed>>
     */
    abstract protected function rows(ReportFilters $filters): Builder|array;

    public function tableTitle(ReportFilters $filters): string
    {
        return $this->title();
    }

    /**
     * group_by filtresinin seçenekleri (yoksa boş).
     *
     * @return array<string, string>
     */
    public function groupByOptions(): array
    {
        return [];
    }

    /**
     * Sorgu satırını tablo satırına çevirir (sütun anahtarlarıyla).
     *
     * @return array<string, mixed>
     */
    protected function mapRow(object $row, ReportFilters $filters): array
    {
        return (array) $row;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function table(ReportFilters $filters, int $page = 1): LengthAwarePaginator
    {
        $rows = $this->rows($filters);

        if ($rows instanceof Builder) {
            return $rows->paginate(self::PER_PAGE, ['*'], 'page', $page)
                ->through(fn (object $row) => $this->mapRow($row, $filters));
        }

        return new LengthAwarePaginator(
            array_values(array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE)),
            count($rows),
            self::PER_PAGE,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Excel için tüm satırlar.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function allRows(ReportFilters $filters): iterable
    {
        $rows = $this->rows($filters);

        if (! $rows instanceof Builder) {
            yield from $rows;

            return;
        }

        foreach ($rows->cursor() as $row) {
            yield $this->mapRow($row, $filters);
        }
    }

    // ------------------------------------------------------------------
    // Ortak sorgu parçaları
    // ------------------------------------------------------------------

    /**
     * Sipariş para birimindeki tutarı ana para birimine (siparişteki kurlarla)
     * çeviren SQL ifadesi.
     */
    protected function tl(string $column, string $orders = 'orders'): string
    {
        return Money::sqlToBase($column, $orders, storedBaseRate: $orders !== 'quotes');
    }

    /**
     * Dönemdeki siparişler; $valid: ciroya sayılanlar.
     */
    protected function orders(ReportFilters $filters, bool $valid = true): Builder
    {
        $query = DB::table('orders')
            ->whereBetween('orders.created_at', [$filters->from, $filters->to]);

        if ($valid) {
            $this->valid($query);
        }

        return $this->applyCustomer($query, $filters, 'orders.account_id');
    }

    /** Ciroya sayılan siparişler (başlangıç ekranıyla aynı tanım). */
    protected function valid(Builder $query, string $orders = 'orders'): Builder
    {
        return $query
            ->whereNotIn("{$orders}.status", [OrderStatus::Cancelled->value, OrderStatus::Failed->value])
            ->where("{$orders}.payment_status", '!=', PaymentStatus::Refunded->value);
    }

    /**
     * Müşteri tipi / bayi grubu filtresi (hesap sütunu üzerinden).
     */
    protected function applyCustomer(Builder $query, ReportFilters $filters, string $accountColumn): Builder
    {
        if ($filters->customerType === 'guest') {
            $query->whereNull($accountColumn);
        } elseif ($filters->customerType !== null) {
            $query->whereIn($accountColumn, DB::table('accounts')->select('id')->where('type', $filters->customerType));
        }

        if ($filters->dealerGroup !== null) {
            $query->whereIn($accountColumn, DB::table('accounts')->select('id')->where('dealer_group_id', $filters->dealerGroup));
        }

        return $query;
    }

    /**
     * Seçili kategori ve alt kategorilerinin id'leri.
     *
     * @return array<int, string>
     */
    protected function categoryIds(string $categoryId): array
    {
        $byParent = DB::table('categories')->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$categoryId];
        $stack = [$categoryId];

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($byParent->get($current, collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    // ------------------------------------------------------------------
    // Çıktı yardımcıları
    // ------------------------------------------------------------------

    /**
     * Para / yüzde değerleri kesin ondalık (JSON'da metin), adetler tam sayı.
     *
     * @return array{label: string, value: BigDecimal|int|float, type: string, icon: string, change: float|null, hint: string|null}
     */
    protected function kpi(string $label, BigNumber|int|float $value, string $type, string $icon, ?float $change = null, ?string $hint = null): array
    {
        return [
            'label' => $label,
            'value' => $type === 'money' ? Money::round($value) : ($type === 'percent' ? Money::round($value, 1) : $value),
            'type' => $type,
            'icon' => $icon,
            'change' => $change,
            'hint' => $hint,
        ];
    }

    /** Önceki döneme göre yüzde değişim; önceki dönem 0 ise null. */
    protected function change(BigNumber|int|float $current, BigNumber|int|float $previous): ?float
    {
        return Money::change($current, $previous);
    }

    /** Pay yüzdesi (1 hane, kesin hesap); bütün sıfırsa 0. */
    protected function percent(BigNumber|int|float $part, BigNumber|int|float $whole): float
    {
        return Money::div(Money::decimal($part)->multipliedBy(100), $whole, 1)->toFloat();
    }

    /**
     * @return array{key: string, label: string, type: string}
     */
    protected function column(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{title: string, columns: array<int, array<string, string>>, rows: array<int, array<string, mixed>>}
     */
    protected function breakdown(string $title, array $columns, array $rows): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    /** Uzun dönemlerde grafik / dönem tablosu aylık gruplanır. */
    protected function monthly(ReportFilters $filters): bool
    {
        return $filters->days() > 92;
    }

    /**
     * Günlük toplamları (gün => değerler) dönem dilimlerine yayar; boş
     * günler / aylar 0 ile doldurulur.
     *
     * Tutar anahtarları kesin toplanır (BigDecimal), adetler tam sayı kalır.
     *
     * @param  Collection<string, array<string, BigNumber|int|float|string>>  $daily  'Y-m-d' => [anahtar => değer]
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $moneyKeys  tutar sütunları (kesin toplanır)
     * @return array<int, array<string, mixed>>
     */
    protected function buckets(ReportFilters $filters, Collection $daily, array $keys, array $moneyKeys = []): array
    {
        $monthly = $this->monthly($filters);
        // Tutar sütunları boş günde de kesin sıfır (JSON'da "0.00"); adetler 0.
        $empty = [...array_fill_keys($keys, 0), ...array_fill_keys(array_intersect($moneyKeys, $keys), Money::round(0))];
        $buckets = [];

        $cursor = $monthly ? $filters->from->startOfMonth() : $filters->from->startOfDay();
        $end = $filters->to->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $id = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$id] = [
                'period' => $id,
                'label' => $monthly ? $this->monthName($cursor) : $cursor->format('d.m.Y'),
                ...$empty,
            ];
            $cursor = $monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        foreach ($daily as $day => $values) {
            $id = $monthly ? substr((string) $day, 0, 7) : (string) $day;

            if (! isset($buckets[$id])) {
                continue;
            }

            foreach ($keys as $key) {
                $value = $values[$key] ?? 0;
                $buckets[$id][$key] = is_int($value) && is_int($buckets[$id][$key])
                    ? $buckets[$id][$key] + $value
                    : Money::add($buckets[$id][$key], $value);
            }
        }

        return array_values(array_map(function (array $bucket) use ($keys) {
            foreach ($keys as $key) {
                if ($bucket[$key] instanceof BigNumber) {
                    $bucket[$key] = Money::round($bucket[$key]);
                }
            }

            return $bucket;
        }, $buckets));
    }

    private function monthName(CarbonImmutable $date): string
    {
        $months = [__('Ocak'), __('Şubat'), __('Mart'), __('Nisan'), __('Mayıs'), __('Haziran'), __('Temmuz'), __('Ağustos'), __('Eylül'), __('Ekim'), __('Kasım'), __('Aralık')];

        return $months[$date->month - 1].' '.$date->year;
    }
}
