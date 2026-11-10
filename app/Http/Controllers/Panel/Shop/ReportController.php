<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Models\DealerGroup;
use App\Services\Panel\Shop\Reports\Report;
use App\Services\Panel\Shop\Reports\ReportCatalog;
use App\Services\Panel\Shop\Reports\ReportExporter;
use App\Services\Panel\Shop\Reports\ReportFilters;
use App\Services\Panel\Shop\Reports\StockReport;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Müşteriler › Raporlar. Her rapor ayrı rota / yetki
 * (panel.shop.reports.{key}); rapor anahtarı rotanın `report` varsayılanıdır.
 */
class ReportController extends Controller
{
    use BuildsShopOptions;

    /**
     * Raporlar bağlantısı: kullanıcının görebildiği ilk rapora yönlendirir.
     */
    public function index(Request $request, ReportCatalog $catalog): RedirectResponse
    {
        $first = array_key_first($catalog->visibleTo($request->user()));

        abort_if($first === null, 403);

        return redirect()->route(ReportCatalog::routeName($first));
    }

    public function show(Request $request, ReportCatalog $catalog): Response
    {
        $report = $this->report($request, $catalog);
        $filters = ReportFilters::fromRequest($request);
        $exportRoute = ReportCatalog::routeName($report->key()).'.export';

        return Inertia::render('panel/shop/reports/show', [
            'currency' => Money::baseCode(),
            'report' => [
                'key' => $report->key(),
                'title' => $report->title(),
                'description' => $report->description(),
                'filters' => $report->filters(),
                'table_title' => $report->tableTitle($filters),
            ],
            'reports' => array_values(array_map(fn (Report $r) => [
                'key' => $r->key(),
                'title' => $r->title(),
                // Göreli yol: sekme vurgusu yolu karşılaştırır (useActiveNavHref).
                'href' => route(ReportCatalog::routeName($r->key()), [], false),
            ], $catalog->visibleTo($request->user()))),
            'filters' => $filters->toArray(),
            'period' => $filters->periodLabel(),
            'options' => $this->options($report),
            'summary' => $report->summary($filters),
            'columns' => $report->columns($filters),
            'table' => $report->table($filters, max(1, (int) $request->query('page', 1)))->withQueryString(),
            'exportUrl' => $request->user()->can($exportRoute)
                ? route($exportRoute, array_filter($filters->toArray(), fn ($v) => $v !== ''))
                : null,
        ]);
    }

    public function export(Request $request, ReportCatalog $catalog, ReportExporter $exporter): BinaryFileResponse
    {
        $report = $this->report($request, $catalog);
        $filters = ReportFilters::fromRequest($request);

        return response()->download($exporter->export($report, $filters), $exporter->filename($report, $filters), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function report(Request $request, ReportCatalog $catalog): Report
    {
        $report = $catalog->find((string) $request->route('report'));

        abort_if($report === null, 404);

        return $report;
    }

    /**
     * Raporun kullandığı filtrelerin seçenekleri.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function options(Report $report): array
    {
        $uses = array_flip($report->filters());
        $pairs = fn (array $map) => array_map(fn ($value, $label) => ['value' => (string) $value, 'label' => $label], array_keys($map), $map);

        return [
            'presets' => $pairs(ReportFilters::presets()),
            // Müşteri tipi süzgeci yalnız birden çok tip varsa (vitrin: bireysel / misafir) anlamlı.
            'customer_types' => isset($uses['customer']) && count($types = ReportFilters::customerTypes()) > 1 ? $pairs($types) : [],
            'dealer_groups' => isset($uses['customer'])
                ? DealerGroup::query()->orderBy('name')->get(['id', 'name'])->map(fn (DealerGroup $g) => ['value' => $g->id, 'label' => $g->name])->all()
                : [],
            'warehouses' => isset($uses['warehouse']) ? $this->warehouseOptions() : [],
            'categories' => isset($uses['category']) ? $this->categoryOptions() : [],
            'brands' => isset($uses['brand']) ? $this->brandOptions() : [],
            'group_by' => $pairs($report->groupByOptions()),
            'stock_states' => isset($uses['stock_state']) ? $pairs(StockReport::states()) : [],
            // Kritik stok eşiği filtresinin varsayılanı (eşitse sorgu dizesine yazılmaz).
            'default_threshold' => (string) ShopSettings::int('low_stock_threshold'),
        ];
    }
}
