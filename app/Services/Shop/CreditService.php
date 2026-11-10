<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\LimitAction;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Order;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use App\Support\Payments\Gateways\OnAccountGateway;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Carbon\CarbonImmutable;

/**
 * Vadeli çalışma: risk, kullanılabilir limit, vadesi geçmiş
 * borç ve vadeli siparişin onay kararı.
 *
 * Risk = cari bakiye + onay bekleyen vadeli siparişler + portföydeki
 * (tahsil edilmemiş) çek / senetler (`shop.ledger.instrument_risk` filtresi).
 */
class CreditService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /**
     * `locking`: kilitli okuma (MySQL REPEATABLE READ'de işlemin eski anlık görüntüsü
     * yerine son işlenmiş veri). Sipariş anındaki limit kararı bununla alınır ki
     * aynı hesabın eşzamanlı iki siparişi birlikte limiti aşamasın.
     */
    public function risk(Account $account, ?Order $exclude = null, bool $locking = false): BigDecimal
    {
        $pending = Order::query()->withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('status', OrderStatus::AwaitingApproval)
            ->where('payment_method', OnAccountGateway::KEY)
            ->when($exclude !== null, fn ($q) => $q->whereKeyNot($exclude->id))
            ->when($locking, fn ($q) => $q->sharedLock())
            ->get(['id', 'grand_total', 'currency_code', 'exchange_rate', 'base_exchange_rate'])
            ->map(fn (Order $o) => $this->ledger->orderAmount($o, $o->grand_total));

        return Money::add($this->ledger->balance($account->id, locking: $locking), ...$pending, ...[self::instrumentRisk($account)]);
    }

    /** Portföydeki (tahsil edilmemiş) çek / senet riski: `shop.ledger.instrument_risk` filtresi. */
    public static function instrumentRisk(Account $account): BigDecimal
    {
        return Money::round(hooks()->apply('shop.ledger.instrument_risk', BigDecimal::zero(), account: $account));
    }

    /** Kullanılabilir limit; limitsizse null. */
    public function available(Account $account): ?BigDecimal
    {
        $limit = CreditTerms::for($account)->limit;

        return $limit === null ? null : Money::sub($limit, $this->risk($account));
    }

    /** Risk + yeni tutar limiti aşıyor mu (kuruş kesin). */
    public static function exceeds(BigNumber|string $risk, BigNumber|string $amount, BigNumber|string $limit): bool
    {
        return Money::add($risk, $amount)->isGreaterThan(Money::round($limit));
    }

    /**
     * Vadesi geçmiş açık borç (yalnız vadeli borçlar: sipariş).
     *
     * @return array{amount: BigDecimal, count: int}
     */
    public function overdue(Account $account): array
    {
        $amount = Money::round(0);
        $count = 0;

        foreach ($this->openOrderDebits($account) as $row) {
            if ($row['due']->isBefore(CarbonImmutable::today())) {
                $amount = $amount->plus($row['open']);
                $count++;
            }
        }

        return ['amount' => $amount, 'count' => $count];
    }

    /**
     * Vadeli siparişin kararı: limit içinde ve vadesi geçmiş borç yoksa onaylanır;
     * değilse hesabın ayarına göre onaya düşer ya da engellenir.
     *
     * @return array{action: ?LimitAction, reason: ?string}
     */
    public function decide(Account $account, BigNumber|string $orderAmount, ?Order $exclude = null, bool $locking = false): array
    {
        $terms = CreditTerms::for($account);
        $reasons = [];
        $action = null;

        if ($terms->limit !== null) {
            $risk = $this->risk($account, $exclude, $locking);

            if (self::exceeds($risk, $orderAmount, $terms->limit)) {
                $reasons[] = __('kredi limiti aşılıyor (limit :limit, kullanılabilir :available)', ['limit' => Money::format($terms->limit), 'available' => Money::format(Money::max(0, Money::sub($terms->limit, $risk)))]);
                $action = $terms->overLimit;
            }
        }

        if ($terms->overdue !== LimitAction::Allow) {
            $overdue = $this->overdue($account);

            if ($overdue['amount']->isPositive()) {
                $reasons[] = __('vadesi geçmiş :amount borç var', ['amount' => Money::format($overdue['amount'])]);
                $action = $action === LimitAction::Block ? $action : $terms->overdue;
            }
        }

        return ['action' => $action, 'reason' => $reasons === [] ? null : ucfirst(implode('; ', $reasons))];
    }

    /**
     * Vadesi geçmiş ve yaklaşan açık borçlar (sipariş bazında).
     *
     * @return array{overdue: array<int, array<string, mixed>>, upcoming: array<int, array<string, mixed>>}
     */
    public function dues(Account $account, int $warningDays): array
    {
        $today = CarbonImmutable::today();
        $overdue = [];
        $upcoming = [];

        foreach ($this->openOrderDebits($account) as $row) {
            $days = (int) $today->diffInDays($row['due'], false);
            $item = [
                'id' => $row['transaction']->id,
                'order' => $row['order'] !== null ? ['id' => $row['order']->id, 'number' => $row['order']->number] : null,
                'due_date' => $row['due']->toDateString(),
                'days' => abs($days),
                'open' => Money::format($row['open']),
                'total' => Money::format($row['transaction']->amount),
            ];

            if ($days < 0) {
                $overdue[] = $item;
            } elseif ($days <= $warningDays) {
                $upcoming[] = $item;
            }
        }

        return ['overdue' => $overdue, 'upcoming' => $upcoming];
    }

    /**
     * Açık sipariş borçları (vade sırasıyla).
     *
     * @return array<int, array{transaction: AccountTransaction, order: ?Order, due: CarbonImmutable, open: BigDecimal}>
     */
    private function openOrderDebits(Account $account): array
    {
        $open = $this->ledger->openDebits($account->id);

        if ($open === []) {
            return [];
        }

        return AccountTransaction::query()->withoutGlobalScopes()
            ->whereKey(array_keys($open))
            ->where('direction', TransactionDirection::Debit)
            ->where('type', TransactionType::Order)
            ->whereNotNull('due_date')
            ->with('source')
            ->orderBy('due_date')
            ->get()
            ->map(fn (AccountTransaction $t) => [
                'transaction' => $t,
                'order' => $t->source instanceof Order ? $t->source : null,
                'due' => $t->due_date,
                'open' => $open[$t->id],
            ])
            ->all();
    }
}
