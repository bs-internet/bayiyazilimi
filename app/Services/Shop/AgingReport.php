<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\TransactionDirection;
use App\Enums\UserType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Support\Money;
use App\Support\SpreadsheetRow;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Yaşlandırma raporu: açık (FIFO ile kapanmamış) borçların
 * vadesine göre dağılımı — vadesi gelmemiş, 0–30, 31–60, 61–90, 90+ gün.
 * Vadesi olmayan borç (dekont, açılış) işlem tarihinden yaşlandırılır.
 */
class AgingReport
{
    /**
     * Vade dilimleri: anahtar => etiket.
     *
     * @return array<string, string>
     */
    public static function buckets(): array
    {
        return ['not_due' => __('Vadesi gelmemiş'), 'd30' => __('1–30 gün'), 'd60' => __('31–60 gün'), 'd90' => __('61–90 gün'), 'd90p' => __('90+ gün')];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, BigDecimal>}
     */
    public function build(): array
    {
        $today = CarbonImmutable::today();
        $allocated = DB::table('account_allocations')->groupBy('debit_id')->selectRaw('debit_id, sum(amount) as total')->pluck('total', 'debit_id');
        $accounts = [];

        AccountTransaction::query()
            ->where('direction', TransactionDirection::Debit)
            ->whereNull('reversed_at')->whereNull('reversal_of')
            ->orderBy('id')
            ->get(['id', 'account_id', 'amount', 'due_date', 'transaction_date'])
            ->each(function (AccountTransaction $t) use (&$accounts, $allocated, $today): void {
                $openMoney = Money::of($t->amount)->minus(Money::of($allocated[$t->id] ?? 0));

                if (! $openMoney->isPositive()) {
                    return;
                }

                $days = (int) ($t->due_date ?? $t->transaction_date)->diffInDays($today, false);
                // Vade günü dahil vadesi gelmemiş sayılır (CreditService::overdue ile aynı kural).
                $bucket = match (true) {
                    $days <= 0 => 'not_due',
                    $days <= 30 => 'd30',
                    $days <= 60 => 'd60',
                    $days <= 90 => 'd90',
                    default => 'd90p',
                };

                $accounts[$t->account_id] ??= array_fill_keys([...array_keys(self::buckets()), 'total'], Money::round(0));
                $accounts[$t->account_id][$bucket] = Money::add($accounts[$t->account_id][$bucket], $openMoney->getAmount());
                $accounts[$t->account_id]['total'] = Money::add($accounts[$t->account_id]['total'], $openMoney->getAmount());
            });

        $info = Account::query()->whereKey(array_keys($accounts))
            ->with(['users' => fn ($q) => $q->where('user_type', UserType::Member->value)->select('id', 'account_id', 'name')])
            ->get(['id', 'title', 'code'])->keyBy('id');

        $rows = [];
        $totals = array_fill_keys([...array_keys(self::buckets()), 'total'], Money::round(0));

        foreach ($accounts as $id => $amounts) {
            $account = $info[$id] ?? null;
            $rows[] = [
                'account_id' => $id,
                'title' => $account?->title ?? $account?->users->first()?->name ?? $account?->code,
                'code' => $account?->code,
                'member_id' => $account?->users->first()?->id,
                ...$amounts,
            ];

            foreach ($amounts as $key => $value) {
                $totals[$key] = Money::add($totals[$key], $value);
            }
        }

        usort($rows, fn (array $a, array $b) => $b['d90p']->compareTo($a['d90p']) ?: $b['total']->compareTo($a['total']));

        return ['rows' => $rows, 'totals' => $totals];
    }

    public function export(): string
    {
        $report = $this->build();
        $path = storage_path('app/private/yaslandirma-'.now()->format('Ymd-His').'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $bold = (new Style)->withFontBold(true);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(SpreadsheetRow::of([__('Geciken ödemeler'), CarbonImmutable::today()->format('d.m.Y'), Money::baseCode()], $bold));
        $writer->addRow(SpreadsheetRow::of([__('Hesap'), __('Kod'), ...array_values(self::buckets()), __('Toplam')], $bold));

        foreach ($report['rows'] as $row) {
            $writer->addRow(SpreadsheetRow::of([$row['title'], $row['code'], ...array_map(fn ($k) => Money::toFloat($row[$k]), array_keys(self::buckets())), Money::toFloat($row['total'])]));
        }

        $writer->addRow(SpreadsheetRow::of([__('Toplam'), '', ...array_map(fn ($k) => Money::toFloat($report['totals'][$k]), array_keys(self::buckets())), Money::toFloat($report['totals']['total'])], $bold));
        $writer->close();

        return $path;
    }
}
