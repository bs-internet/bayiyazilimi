<?php

declare(strict_types=1);

namespace App\Services\Panel\Shop\Reports;

use App\Support\Money;
use App\Support\SpreadsheetRow;
use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Raporu Excel'e yazar: 1. sayfa "Özet" (dönem, filtre, KPI'lar, kırılımlar),
 * 2. sayfa ana tablo (tüm satırlar). Sayılar sayı hücresi olarak yazılır;
 * Excel'de toplama / süzme yapılabilir.
 */
final class ReportExporter
{
    public function export(Report $report, ReportFilters $filters): string
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $path = storage_path('app/private/rapor-'.$report->key().'-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $bold = (new Style)->withFontBold(true);
        $title = (new Style)->withFontBold(true)->withFontSize(14);

        $writer = new Writer;
        $writer->openToFile($path);

        // --- Özet ---
        $writer->getCurrentSheet()->setName(__('Özet'));
        $summary = $report->summary($filters);

        $writer->addRow(SpreadsheetRow::of([$report->title()], $title));
        $writer->addRow(SpreadsheetRow::of([__('Dönem'), $filters->periodLabel()]));
        $writer->addRow(SpreadsheetRow::of([__('Oluşturma'), now()->format('d.m.Y H:i')]));
        $writer->addRow(SpreadsheetRow::of([]));

        $writer->addRow(SpreadsheetRow::of([__('Gösterge'), __('Değer'), __('Önceki döneme göre %')], $bold));
        foreach ($summary['kpis'] as $kpi) {
            $writer->addRow(SpreadsheetRow::of([$kpi['label'], self::cell($kpi['value']), $kpi['change']]));
        }

        foreach ($summary['breakdowns'] as $breakdown) {
            $writer->addRow(SpreadsheetRow::of([]));
            $writer->addRow(SpreadsheetRow::of([$breakdown['title']], $bold));
            $writer->addRow(SpreadsheetRow::of(array_column($breakdown['columns'], 'label'), $bold));
            foreach ($breakdown['rows'] as $row) {
                $writer->addRow($this->row($breakdown['columns'], $row));
            }
        }

        // --- Ana tablo ---
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName(mb_substr($this->sheetName($report->tableTitle($filters)), 0, 31));

        $columns = $report->columns($filters);
        $writer->addRow(SpreadsheetRow::of(array_column($columns, 'label'), $bold));

        foreach ($report->allRows($filters) as $row) {
            $writer->addRow($this->row($columns, $row));
        }

        $writer->close();

        return $path;
    }

    public function filename(Report $report, ReportFilters $filters): string
    {
        return str($report->title())->slug()->append('-', $filters->from->format('Ymd'), '-', $filters->to->format('Ymd'), '.xlsx')->toString();
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @param  array<string, mixed>  $row
     */
    private function row(array $columns, array $row): Row
    {
        $values = [];

        foreach ($columns as $column) {
            $value = $row[$column['key']] ?? null;

            $values[] = match ($column['type']) {
                'date' => $value ? CarbonImmutable::parse($value)->format('d.m.Y') : null,
                'datetime' => $value ? CarbonImmutable::parse($value)->format('d.m.Y H:i') : null,
                default => self::cell($value),
            };
        }

        return SpreadsheetRow::of($values);
    }

    /** Excel hücresi sayı ister: kesin tutar sayısal karşılığıyla yazılır (yalnız sunum). */
    private static function cell(mixed $value): mixed
    {
        return $value instanceof BigNumber ? Money::toFloat($value) : $value;
    }

    /** Excel sayfa adında yasak karakterler. */
    private function sheetName(string $name): string
    {
        return str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name);
    }
}
