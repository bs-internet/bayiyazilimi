<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\TransactionDirection;
use App\Models\Account;
use App\Support\Money;
use App\Support\SpreadsheetRow;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Cari ekstreyi Excel'e yazar: devir, hareketler (yürüyen bakiye), dönem toplamları.
 * Tutarlar ana para birimindedir; bakiye pozitifse müşteri borçlu (B), negatifse alacaklı (A).
 */
class LedgerExporter
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function export(Account $account, ?string $from = null, ?string $to = null): string
    {
        $fromDate = $from ? CarbonImmutable::parse($from) : null;
        $toDate = $to ? CarbonImmutable::parse($to) : null;
        $statement = $this->ledger->statement($account->id, $fromDate, $toDate);

        $path = storage_path('app/private/cari-'.$account->id.'-'.now()->format('Ymd-His').'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $bold = (new Style)->withFontBold(true);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(SpreadsheetRow::of([__('Cari Ekstre'), ($account->title ?? $account->code).' ('.$account->code.')'], $bold));
        $writer->addRow(SpreadsheetRow::of([__('Dönem'), ($fromDate?->format('d.m.Y') ?? __('Başlangıç')).' – '.($toDate?->format('d.m.Y') ?? CarbonImmutable::today()->format('d.m.Y'))]));
        $writer->addRow(SpreadsheetRow::of([__('Para birimi'), Money::baseCode()]));
        $writer->addRow(SpreadsheetRow::of([]));
        $writer->addRow(SpreadsheetRow::of([__('Tarih'), __('Vade'), __('Tür'), __('Açıklama'), __('Borç'), __('Alacak'), __('Bakiye'), 'B/A'], $bold));
        $writer->addRow(SpreadsheetRow::of(['', '', __('Devir'), __('Dönem başı bakiye'), '', '', self::cell($statement['opening']->abs()), self::side($statement['opening'])]));

        foreach ($statement['rows'] as ['transaction' => $t, 'balance' => $balance]) {
            $debit = $t->direction === TransactionDirection::Debit;

            $writer->addRow(SpreadsheetRow::of([
                $t->transaction_date->format('d.m.Y'),
                $t->due_date?->format('d.m.Y') ?? '',
                $t->type->label().($t->reversal_of !== null ? __(' (iptal)') : ''),
                (string) $t->description,
                $debit ? self::cell($t->amount) : '',
                $debit ? '' : self::cell($t->amount),
                self::cell($balance->abs()),
                self::side($balance),
            ]));
        }

        $writer->addRow(SpreadsheetRow::of(['', '', '', __('Dönem toplamı'), self::cell($statement['debit_total']), self::cell($statement['credit_total']), self::cell($statement['closing']->abs()), self::side($statement['closing'])], $bold));
        $writer->close();

        return $path;
    }

    /** Excel hücresi sayı ister: 2 haneli kesin tutarın sayısal karşılığı (yalnız sunum). */
    private static function cell(BigDecimal $amount): float
    {
        return Money::toFloat(Money::round($amount));
    }

    private static function side(BigDecimal $balance): string
    {
        $balance = Money::round($balance);

        return $balance->isZero() ? '' : ($balance->isPositive() ? 'B' : 'A');
    }
}
