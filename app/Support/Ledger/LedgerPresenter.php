<?php

declare(strict_types=1);

namespace App\Support\Ledger;

use App\Enums\Shop\CollectionType;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Services\Shop\CreditService;
use App\Services\Shop\LedgerService;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Cari ekranlarının (panel müşteri detayı, Cari Hesabım, ekstre) ortak verisi.
 */
final class LedgerPresenter
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CreditService $credit,
    ) {}

    /**
     * Özet + ekstre. `from` / `to` boşsa tüm hareketler.
     *
     * @return array<string, mixed>
     */
    public function forAccount(Account $account, ?string $from = null, ?string $to = null, bool $admin = false): array
    {
        $fromDate = self::parseDate($from);
        $toDate = self::parseDate($to);
        $statement = $this->ledger->statement($account->id, $fromDate, $toDate);

        return [
            'account_id' => $account->id,
            'currency' => Money::baseCode(),
            'summary' => $this->summary($account),
            'filters' => ['from' => $fromDate?->toDateString(), 'to' => $toDate?->toDateString()],
            'dues' => $this->credit->dues($account, ShopSettings::int('due_warning_days')),
            'open_orders' => $this->ledger->openOrders($account->id),
            'statement' => [
                'opening' => self::side($statement['opening']),
                'closing' => self::side($statement['closing']),
                'debit_total' => Money::format($statement['debit_total']),
                'credit_total' => Money::format($statement['credit_total']),
                'rows' => array_map(fn (array $r) => $this->row($r['transaction'], $r['balance'], $admin), array_reverse($statement['rows'])),
            ],
        ];
    }

    /**
     * Cari özeti: bakiye (borçlu / alacaklı), çalışma şekli; vadeli hesapta
     * kredi limiti, risk, kullanılabilir limit, vadesi geçen ve onay bekleyen.
     *
     * @return array<string, mixed>
     */
    public function summary(Account $account): array
    {
        $balance = $this->ledger->balance($account->id);
        $terms = CreditTerms::for($account);
        $summary = [
            'balance' => $balance,
            ...self::side($balance),
            'terms' => $terms->terms->value,
            'terms_label' => $terms->terms->label(),
            'term_days' => $terms->isCredit() ? $terms->termDays : null,
        ];

        if (! $terms->isCredit()) {
            return $summary;
        }

        $risk = $this->credit->risk($account);
        $overdue = $this->credit->overdue($account);
        $pending = Money::sub(Money::sub($risk, $balance), CreditService::instrumentRisk($account));

        return [
            ...$summary,
            'credit_limit' => $terms->limit !== null ? Money::format($terms->limit) : null,
            'risk' => Money::format($risk),
            'available' => $terms->limit !== null ? Money::format(Money::max(0, Money::sub($terms->limit, $risk))) : null,
            'over_limit' => $terms->limit !== null && $risk->isGreaterThan($terms->limit),
            'overdue' => $overdue['amount']->isPositive() ? Money::format($overdue['amount']) : null,
            'overdue_count' => $overdue['count'],
            'pending_approval' => $pending->isPositive() ? Money::format($pending) : null,
        ];
    }

    /**
     * Bakiyenin görünümü: tutar (mutlak) + yön.
     *
     * @return array{amount: BigDecimal, formatted: string, side: string, side_label: string}
     */
    public static function side(BigDecimal $balance): array
    {
        $balance = Money::round($balance);
        $side = $balance->isZero() ? 'zero' : ($balance->isPositive() ? 'debit' : 'credit');

        return [
            'amount' => $balance,
            'formatted' => Money::format($balance->abs()),
            'side' => $side,
            'side_label' => match ($side) {
                'debit' => __('Borçlu'),
                'credit' => __('Alacaklı'),
                default => __('Bakiye yok'),
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AccountTransaction $t, BigDecimal $balance, bool $admin): array
    {
        $source = $t->source_type !== null ? $t->source : null;
        $debit = $t->direction === TransactionDirection::Debit;

        return [
            'id' => $t->id,
            'date' => $t->transaction_date->toDateString(),
            'due_date' => $t->due_date?->toDateString(),
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'description' => $t->description,
            'debit' => $debit ? Money::format($t->amount) : null,
            'credit' => $debit ? null : Money::format($t->amount),
            'original' => $t->original_currency !== null && $t->original_currency !== $t->currency_code
                ? Money::number($t->original_amount).' '.$t->original_currency
                : null,
            'balance' => self::side($balance),
            'order' => match (true) {
                $source instanceof Order => ['id' => $source->id, 'number' => $source->number],
                $source instanceof Payment && $source->order !== null => ['id' => $source->order->id, 'number' => $source->order->number],
                $source instanceof ReturnRequest && $source->order !== null => ['id' => $source->order->id, 'number' => $source->order->number],
                default => null,
            },
            'reversed' => $t->reversed_at !== null,
            'is_reversal' => $t->reversal_of !== null,
            'reversible' => $admin && $this->reversible($t, $source),
            'user' => $admin ? $t->user?->name : null,
            'origin' => $t->origin !== 'system' ? $t->origin : null,
        ];
    }

    private function reversible(AccountTransaction $t, mixed $source): bool
    {
        if ($t->reversed_at !== null || $t->reversal_of !== null) {
            return false;
        }

        return in_array($t->type, [TransactionType::Adjustment, TransactionType::Opening], true)
            || ($t->type === TransactionType::Payment && $t->isExternal())
            || ($source instanceof Payment && $source->order_id === null && $source->provider === 'manual'
                && ! (CollectionType::tryFrom($source->method)?->isInstrument() ?? false));
    }

    private static function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
