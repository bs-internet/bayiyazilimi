<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Models\User;

/**
 * Panel raporları (menü sırasıyla). Her raporun rotası ve yetkisi
 * `panel.shop.reports.{key}`, Excel'i `panel.shop.reports.{key}.export`.
 */
final class ReportCatalog
{
    /** @var array<int, class-string<Report>> */
    private const REPORTS = [
        SalesReport::class,
        ProductSalesReport::class,
        CustomerReport::class,
        StockReport::class,
        QuoteReport::class,
        CampaignReport::class,
        ReturnReport::class,
    ];

    /**
     * @return array<string, Report> anahtar => rapor
     */
    public function all(): array
    {
        $reports = [];

        foreach (self::REPORTS as $class) {
            $report = app($class);
            $reports[$report->key()] = $report;
        }

        return $reports;
    }

    public function find(string $key): ?Report
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Kullanıcının görebildiği raporlar.
     *
     * @return array<string, Report>
     */
    public function visibleTo(?User $user): array
    {
        return array_filter($this->all(), fn (Report $report) => $user?->can(self::routeName($report->key())) ?? false);
    }

    public static function routeName(string $key): string
    {
        return "panel.shop.reports.{$key}";
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return ['sales', 'products', 'customers', 'stock', 'quotes', 'campaigns', 'returns'];
    }
}
