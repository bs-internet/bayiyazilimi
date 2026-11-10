<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Events\Shop\AccountTransactionRecordedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use App\Support\Payments\Gateways\OnAccountGateway;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Money\Money as BrickMoney;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cari hesap defteri. Müşterinin tüm aldı / verdi hareketleri
 * buradan yazılır; bakiye = Σ borç − Σ alacak (pozitif: müşteri borçlu).
 *
 * Kurallar: sipariş borcu "Hazırlanıyor"a geçince, ödeme "ödendi" olunca
 * alacak; iptal / iade ters kayıt. Kayıt silinmez. ERP modülleri `record()`
 * ile `origin` + `external_ref` vererek yazar (aynı kayıt iki kez yazılmaz).
 */
class LedgerService
{
    /**
     * Genel hareket kaydı.
     *
     * @param  BigNumber|int|string  $amount  ana para biriminde (kesin; float kabul edilmez)
     * @param  array{date?: DateTimeInterface|string|null, due_date?: DateTimeInterface|string|null, source?: ?Model, original_amount?: BigNumber|string|null, original_currency?: ?string, exchange_rate?: BigNumber|string|null, origin?: string, external_ref?: ?string, description?: ?string, user?: ?User}  $options
     */
    public function record(Account|string $account, TransactionType $type, TransactionDirection $direction, BigNumber|int|string $amount, array $options = []): AccountTransaction
    {
        $accountId = $account instanceof Account ? $account->id : $account;
        $origin = $options['origin'] ?? 'system';
        $externalRef = $options['external_ref'] ?? null;
        $money = Money::of($amount);

        if (! $money->isPositive()) {
            throw new ShopException(__('Cari hareket tutarı sıfırdan büyük olmalıdır.'));
        }

        return DB::transaction(function () use ($accountId, $type, $direction, $money, $origin, $externalRef, $options): AccountTransaction {
            // Hesabın cari yazımları sıraya girer: aynı kaydın iki kez yazılması ve
            // dağıtımın (reallocate) iç içe geçmesi önlenir.
            $this->lockAccount($accountId);

            if ($externalRef !== null) {
                $existing = AccountTransaction::query()->withoutGlobalScopes()
                    ->where('origin', $origin)->where('external_ref', $externalRef)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $source = $options['source'] ?? null;

            $transaction = AccountTransaction::query()->withoutGlobalScopes()->create([
                'account_id' => $accountId,
                'type' => $type,
                'direction' => $direction,
                'amount' => (string) $money->getAmount(),
                'currency_code' => Money::baseCode(),
                'original_amount' => isset($options['original_amount']) ? Money::str($options['original_amount']) : null,
                'original_currency' => $options['original_currency'] ?? null,
                'exchange_rate' => isset($options['exchange_rate']) ? (string) Money::decimal($options['exchange_rate']) : null,
                'transaction_date' => self::date($options['date'] ?? null),
                'due_date' => isset($options['due_date']) ? self::date($options['due_date']) : null,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'origin' => $origin,
                'external_ref' => $externalRef,
                'description' => isset($options['description']) ? mb_substr((string) $options['description'], 0, 255) : null,
                'user_id' => ($options['user'] ?? null)?->id,
            ]);

            $this->reallocate($accountId);
            AccountTransactionRecordedEvent::dispatch($transaction);

            return $transaction;
        });
    }

    /**
     * Ters kayıt: aynı tutarda karşı yönde hareket; asıl kayıt işaretlenir.
     */
    public function reverse(AccountTransaction $transaction, ?User $user = null, ?string $description = null): AccountTransaction
    {
        if ($transaction->reversed_at !== null || $transaction->reversal_of !== null) {
            throw new ShopException(__('Bu cari hareketin ters kaydı zaten yapılmış.'));
        }

        return DB::transaction(function () use ($transaction, $user, $description) {
            $this->lockAccount($transaction->account_id);

            // Koşullu işaret: eşzamanlı iki ters kayıt isteğinden yalnız biri geçer
            // (ikincisi 0 satır günceller); `reversal_of` ayrıca tekil indekslidir.
            $marked = AccountTransaction::query()->withoutGlobalScopes()
                ->whereKey($transaction->id)
                ->whereNull('reversed_at')
                ->whereNull('reversal_of')
                ->update(['reversed_at' => now()]);

            if ($marked === 0) {
                throw new ShopException(__('Bu cari hareketin ters kaydı zaten yapılmış.'));
            }

            $transaction->refresh();

            $reversal = AccountTransaction::query()->withoutGlobalScopes()->create([
                'account_id' => $transaction->account_id,
                'type' => $transaction->type,
                'direction' => $transaction->direction->opposite(),
                'amount' => $transaction->amount,
                'currency_code' => $transaction->currency_code,
                'original_amount' => $transaction->original_amount,
                'original_currency' => $transaction->original_currency,
                'exchange_rate' => $transaction->exchange_rate,
                'transaction_date' => CarbonImmutable::today(),
                'source_type' => $transaction->source_type,
                'source_id' => $transaction->source_id,
                'origin' => $transaction->origin,
                'reversal_of' => $transaction->id,
                'description' => mb_substr($description ?? __('İptal: :description', ['description' => $transaction->description ?? $transaction->type->label()]), 0, 255),
                'user_id' => $user?->id,
            ]);

            $this->reallocate($transaction->account_id);
            AccountTransactionRecordedEvent::dispatch($reversal);

            return $reversal;
        });
    }

    /**
     * Sipariş borcu (sipariş kesinleşince: Hazırlanıyor / Kargoda / Tamamlandı).
     * Hesabı olmayan (misafir) siparişte ve zaten yazılmışsa bir şey yapmaz.
     */
    public function recordOrder(Order $order): ?AccountTransaction
    {
        if ($order->account_id === null || ! Money::decimal($order->grand_total)->isPositive()) {
            return null;
        }

        return DB::transaction(fn (): AccountTransaction => $this->recordOrderLocked($order));
    }

    private function recordOrderLocked(Order $order): AccountTransaction
    {
        $this->lockAccount($order->account_id);

        $existing = $this->effectiveFor($order, TransactionType::Order);

        if ($existing !== null) {
            return $existing;
        }

        // Vade: vadeli siparişte bugün + vade günü, diğerlerinde bugün.
        $due = $order->due_date ?? ($order->payment_method === OnAccountGateway::KEY
            ? CreditTerms::for(Account::query()->with('dealerGroup')->find($order->account_id))->dueDate()
            : CarbonImmutable::today());

        if ($order->due_date === null) {
            $order->forceFill(['due_date' => $due])->saveQuietly();
        }

        return $this->record($order->account_id, TransactionType::Order, TransactionDirection::Debit, $this->orderAmount($order, $order->grand_total), [
            'source' => $order,
            'due_date' => $due,
            'original_amount' => $order->grand_total,
            'original_currency' => $order->currency_code,
            'exchange_rate' => $order->exchange_rate,
            'description' => __('Sipariş :number', ['number' => $order->number]),
        ]);
    }

    public function reverseOrder(Order $order, ?User $user = null): void
    {
        $existing = $this->effectiveFor($order, TransactionType::Order);

        if ($existing !== null) {
            $this->reverse($existing, $user, __('Sipariş :number iptal edildi', ['number' => $order->number]));
        }
    }

    /**
     * Sipariş durumuna göre borcu eşitler: kesinleşmiş sipariş borçlanır,
     * iptal edilen siparişin borcu ters kayıtla kapanır.
     */
    public function syncOrder(Order $order, ?User $user = null): void
    {
        if (in_array($order->status, [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed], true)) {
            $this->recordOrder($order);
        } elseif ($order->status === OrderStatus::Cancelled) {
            $this->reverseOrder($order, $user);
        }
    }

    /**
     * Tahsilat (ödeme "ödendi"): alacak.
     */
    public function recordPayment(Payment $payment): ?AccountTransaction
    {
        $accountId = $payment->account_id ?? $payment->order?->account_id;

        if ($accountId === null || ! Money::decimal($payment->amount)->isPositive()) {
            return null;
        }

        return DB::transaction(fn (): AccountTransaction => $this->recordPaymentLocked($payment, $accountId));
    }

    private function recordPaymentLocked(Payment $payment, string $accountId): AccountTransaction
    {
        $this->lockAccount($accountId);

        $existing = $this->effectiveFor($payment, TransactionType::Payment);

        if ($existing !== null) {
            return $existing;
        }

        $order = $payment->order;
        $amount = $order !== null && $order->currency_code === $payment->currency_code
            ? $this->orderAmount($order, $payment->amount)
            : Money::toBase($payment->amount, $payment->currency());

        return $this->record($accountId, TransactionType::Payment, TransactionDirection::Credit, $amount, [
            'source' => $payment,
            'date' => $payment->paid_at ?? now(),
            'original_amount' => $payment->amount,
            'original_currency' => $payment->currency_code,
            'description' => trim(($order !== null
                ? __('Tahsilat — :method (Sipariş :number)', ['method' => $payment->methodLabel(), 'number' => $order->number])
                : __('Tahsilat — :method', ['method' => $payment->methodLabel()])).($payment->note ? ' · '.$payment->note : '')),
            'user' => $payment->user,
        ]);
    }

    public function reversePayment(Payment $payment, ?User $user = null, ?string $description = null): void
    {
        $existing = $this->effectiveFor($payment, TransactionType::Payment);

        if ($existing !== null) {
            $this->reverse($existing, $user, $description ?? __('Tahsilat iptal / iade: :method', ['method' => $payment->methodLabel()]));
        }
    }

    /**
     * İade talebi tamamlandı: iade tutarı kadar alacak (sipariş kuruyla); para
     * da iade edildiyse aynı tutarda "para iadesi" borcu.
     */
    public function recordReturn(ReturnRequest $return, BigNumber|int|string $amount, bool $refunded, User $user): void
    {
        $order = $return->order;
        $amount = Money::round($amount);

        if ($order === null || $order->account_id === null || ! $amount->isPositive()) {
            return;
        }

        DB::transaction(function () use ($return, $order, $amount, $refunded, $user): void {
            $this->lockAccount($order->account_id);

            if ($this->effectiveFor($return, TransactionType::ReturnCredit) === null) {
                $this->writeReturn($return, $order, $amount, $refunded, $user);
            }
        });
    }

    private function writeReturn(ReturnRequest $return, Order $order, BigDecimal $amount, bool $refunded, User $user): void
    {

        $base = $this->orderAmount($order, $amount);
        $common = [
            'source' => $return,
            'original_amount' => $amount,
            'original_currency' => $order->currency_code,
            'exchange_rate' => $order->exchange_rate,
            'user' => $user,
        ];

        $this->record($order->account_id, TransactionType::ReturnCredit, TransactionDirection::Credit, $base, [
            ...$common,
            'description' => __('İade :number (Sipariş :number2)', ['number' => $return->number, 'number2' => $order->number]),
        ]);

        if ($refunded) {
            $this->record($order->account_id, TransactionType::Refund, TransactionDirection::Debit, $base, [
                ...$common,
                'description' => __('İade :number — ödeme müşteriye geri yapıldı', ['number' => $return->number]),
            ]);
        }
    }

    /**
     * Cari bakiye (ana para biriminde): pozitif müşteri borçlu, negatif alacaklı.
     */
    public function balance(string $accountId, ?DateTimeInterface $until = null, bool $locking = false): BigDecimal
    {
        // `locking`: kilitli okuma — MySQL REPEATABLE READ'de işlemin eski anlık
        // görüntüsü yerine son işlenmiş veriyi okur (yazmadan önceki limit denetimi).
        $row = AccountTransaction::query()->withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->when($until !== null, fn ($q) => $q->whereDate('transaction_date', '<=', $until))
            ->when($locking, fn ($q) => $q->sharedLock())
            ->selectRaw("coalesce(sum(case when direction = 'debit' then amount else -amount end), 0) as balance")
            ->toBase()
            ->first();

        return Money::round($row->balance ?? 0);
    }

    /**
     * Birden çok hesabın bakiyesi (liste ekranları).
     *
     * @param  array<int, string>  $accountIds
     * @return array<string, BigDecimal>
     */
    public function balances(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return AccountTransaction::query()->withoutGlobalScopes()
            ->whereIn('account_id', $accountIds)
            ->groupBy('account_id')
            ->selectRaw("account_id, sum(case when direction = 'debit' then amount else -amount end) as balance")
            ->toBase()
            ->pluck('balance', 'account_id')
            ->map(fn ($v) => Money::round($v))
            ->all();
    }

    /**
     * Ekstre: dönem başı devir + dönem hareketleri (yürüyen bakiye) + dönem sonu.
     *
     * @return array{opening: BigDecimal, closing: BigDecimal, debit_total: BigDecimal, credit_total: BigDecimal, rows: array<int, array{transaction: AccountTransaction, balance: BigDecimal}>}
     */
    public function statement(string $accountId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $opening = $from !== null ? $this->balance($accountId, CarbonImmutable::instance($from)->subDay()) : Money::round(0);

        $transactions = AccountTransaction::query()->withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->when($from !== null, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to !== null, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->with(['user:id,name', 'source' => fn (MorphTo $morph) => $morph->morphWith([
                Payment::class => ['order:id,number'],
                ReturnRequest::class => ['order:id,number'],
            ])])
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $running = Money::of($opening);
        $debit = Money::zero();
        $credit = Money::zero();
        $rows = [];

        foreach ($transactions as $t) {
            $amount = Money::of($t->amount);

            if ($t->direction === TransactionDirection::Debit) {
                $running = $running->plus($amount);
                $debit = $debit->plus($amount);
            } else {
                $running = $running->minus($amount);
                $credit = $credit->plus($amount);
            }

            $rows[] = ['transaction' => $t, 'balance' => $running->getAmount()];
        }

        return [
            'opening' => $opening,
            'closing' => $running->getAmount(),
            'debit_total' => $debit->getAmount(),
            'credit_total' => $credit->getAmount(),
            'rows' => $rows,
        ];
    }

    /**
     * FIFO dağıtımı: önce siparişe bağlı ödemeler / iadeler kendi
     * siparişini, ardından tahsilatlar seçilen siparişleri kapatır; kalan
     * alacaklar en eski vadeli borçtan başlayarak dağıtılır. Her harekette hesap için baştan hesaplanır; ardından siparişlerin
     * ödeme durumu (Bekliyor / Kısmen / Ödendi) eşitlenir.
     */
    public function reallocate(string $accountId): void
    {
        $rows = AccountTransaction::query()->withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->whereNull('reversed_at')->whereNull('reversal_of')
            ->orderBy('transaction_date')->orderBy('created_at')->orderBy('id')
            ->get(['id', 'type', 'direction', 'amount', 'due_date', 'transaction_date', 'source_type', 'source_id', 'created_at']);

        $debits = $rows->where('direction', TransactionDirection::Debit)
            ->sortBy(fn (AccountTransaction $t) => [($t->due_date ?? $t->transaction_date)->toDateString(), $t->transaction_date->toDateString(), (string) $t->created_at])
            ->values();
        $credits = $rows->where('direction', TransactionDirection::Credit)->values();

        // Kesin tutarlar (brick/money): uzun FIFO zincirinde kuruş kayması olmaz.
        $open = $debits->mapWithKeys(fn (AccountTransaction $t) => [$t->id => Money::of($t->amount)])->all();
        $left = $credits->mapWithKeys(fn (AccountTransaction $t) => [$t->id => Money::of($t->amount)])->all();

        $orderMorph = (new Order)->getMorphClass();
        $orderDebit = $debits->where('type', TransactionType::Order)->where('source_type', $orderMorph)->pluck('id', 'source_id')->all();

        // Alacağın bağlı olduğu sipariş: siparişin ödemesi ya da iadesi.
        $paymentOrders = Payment::query()->withoutGlobalScopes()
            ->whereIn('id', $credits->where('source_type', (new Payment)->getMorphClass())->pluck('source_id'))
            ->whereNotNull('order_id')->pluck('order_id', 'id')->all();
        $returnOrders = ReturnRequest::query()->withoutGlobalScopes()
            ->whereIn('id', $credits->where('source_type', (new ReturnRequest)->getMorphClass())->pluck('source_id'))
            ->pluck('order_id', 'id')->all();

        $allocations = [];
        $allocate = function (string $debitId, string $creditId) use (&$open, &$left, &$allocations): void {
            $amount = BrickMoney::min($open[$debitId], $left[$creditId]);

            if (! $amount->isPositive()) {
                return;
            }

            $open[$debitId] = $open[$debitId]->minus($amount);
            $left[$creditId] = $left[$creditId]->minus($amount);
            $allocations[] = [$debitId, $creditId, (string) $amount->getAmount()];
        };

        foreach ($credits as $credit) {
            $orderId = $paymentOrders[$credit->source_id] ?? $returnOrders[$credit->source_id] ?? null;
            $debitId = $orderId !== null ? ($orderDebit[$orderId] ?? null) : null;

            if ($debitId !== null) {
                $allocate($debitId, $credit->id);
            }
        }

        // Tahsilatta seçilen siparişler, seçim sırasıyla.
        $targets = DB::table('payment_orders')
            ->whereIn('payment_id', $credits->where('source_type', (new Payment)->getMorphClass())->pluck('source_id'))
            ->orderBy('sort_order')
            ->get()
            ->groupBy('payment_id');

        foreach ($credits as $credit) {
            foreach ($targets->get((string) $credit->source_id, []) as $target) {
                if (isset($orderDebit[$target->order_id])) {
                    $allocate($orderDebit[$target->order_id], $credit->id);
                }
            }
        }

        foreach ($credits as $credit) {
            foreach ($debits as $debit) {
                if (! $left[$credit->id]->isPositive()) {
                    break;
                }

                if ($open[$debit->id]->isPositive()) {
                    $allocate($debit->id, $credit->id);
                }
            }
        }

        DB::table('account_allocations')->where('account_id', $accountId)->delete();
        $now = now();

        foreach (array_chunk($allocations, 200) as $chunk) {
            DB::table('account_allocations')->insert(array_map(fn (array $a) => [
                'id' => (string) Str::uuid(),
                'account_id' => $accountId,
                'debit_id' => $a[0],
                'credit_id' => $a[1],
                'amount' => $a[2],
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $this->syncOrderPayments($orderDebit, $open, $debits);
    }

    /**
     * Borcu dağıtıma göre kapanan / kısmen kapanan siparişlerin ödeme durumu.
     * Vadeli siparişlerde her zaman; diğerlerinde yalnız "Bekliyor / Kısmen" iken
     * (ör. ödemesi alınmadan hazırlanan siparişe sonradan tahsilat girilmesi).
     *
     * @param  array<string, string>  $orderDebit  sipariş id → borç hareketi id
     * @param  array<string, BrickMoney>  $open  borç hareketi id → açık tutar
     * @param  Collection<int, AccountTransaction>  $debits
     */
    private function syncOrderPayments(array $orderDebit, array $open, Collection $debits): void
    {
        if ($orderDebit === []) {
            return;
        }

        $amounts = $debits->pluck('amount', 'id');

        Order::query()->withoutGlobalScopes()->whereKey(array_keys($orderDebit))->get()->each(function (Order $order) use ($orderDebit, $open, $amounts): void {
            $debitId = $orderDebit[$order->id];
            $remaining = $open[$debitId] ?? Money::zero();
            $target = match (true) {
                ! $remaining->isPositive() => PaymentStatus::Paid,
                $remaining->isLessThan(Money::of($amounts[$debitId])) => PaymentStatus::Partial,
                default => PaymentStatus::Pending,
            };

            $applies = $order->payment_method === OnAccountGateway::KEY
                ? in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Partial, PaymentStatus::Paid], true)
                : in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Partial], true);

            if (! $applies || $order->payment_status === $target) {
                return;
            }

            // Koşullu yazım: okuduğumuzdan beri durum değiştiyse (kart bildirimi, yönetici) ezilmez.
            $updated = Order::query()->withoutGlobalScopes()->whereKey($order->id)
                ->where('payment_status', $order->payment_status->value)
                ->update([
                    'payment_status' => $target->value,
                    'paid_at' => $target === PaymentStatus::Paid ? ($order->paid_at ?? now()) : $order->paid_at,
                ]);

            if ($updated === 0) {
                return;
            }

            if ($target === PaymentStatus::Paid) {
                $order->histories()->create(['status' => $order->status, 'note' => __('Cari tahsilatla ödemesi kapandı.')]);
            }
        });
    }

    /**
     * Tahsilatın kapatacağı siparişleri kaydeder (seçim sırasıyla). Siparişler
     * ödemenin hesabına ait olmalıdır.
     *
     * @param  array<int, string>  $orderIds
     */
    public function targetOrders(Payment $payment, array $orderIds): void
    {
        $orderIds = array_values(array_unique($orderIds));

        if ($orderIds === []) {
            return;
        }

        $owned = Order::query()->withoutGlobalScopes()->whereKey($orderIds)->where('account_id', $payment->account_id)->count();

        if ($owned !== count($orderIds)) {
            throw new ShopException(__('Seçilen sipariş bulunamadı.'));
        }

        DB::table('payment_orders')->insert(array_map(
            fn (string $orderId, int $index): array => ['payment_id' => $payment->id, 'order_id' => $orderId, 'sort_order' => $index],
            $orderIds,
            array_keys($orderIds),
        ));
    }

    /**
     * Borcu kapanmamış siparişler, en eski vadeden başlayarak (tahsilatta seçim listesi).
     *
     * @return list<array{id: string, number: string, open: string, due_date: ?string}>
     */
    public function openOrders(string $accountId): array
    {
        $open = $this->openDebits($accountId);

        $debits = AccountTransaction::query()->withoutGlobalScopes()
            ->whereKey(array_keys($open))
            ->where('type', TransactionType::Order)
            ->where('source_type', (new Order)->getMorphClass())
            ->get(['id', 'source_id', 'due_date', 'transaction_date'])
            ->sortBy(fn (AccountTransaction $t) => ($t->due_date ?? $t->transaction_date)->toDateString());

        $numbers = Order::query()->withoutGlobalScopes()->whereKey($debits->pluck('source_id'))->pluck('number', 'id');

        return $debits->map(fn (AccountTransaction $t): array => [
            'id' => (string) $t->source_id,
            'number' => (string) ($numbers[$t->source_id] ?? ''),
            'open' => Money::format($open[$t->id]),
            'due_date' => $t->due_date?->format('d.m.Y'),
        ])->values()->all();
    }

    /**
     * Borç hareketlerinin açık (dağıtılmamış) tutarları.
     *
     * @return array<string, BigDecimal> borç hareketi id → açık tutar
     */
    public function openDebits(string $accountId): array
    {
        $allocated = DB::table('account_allocations')->where('account_id', $accountId)
            ->groupBy('debit_id')->selectRaw('debit_id, sum(amount) as total')->pluck('total', 'debit_id');

        return AccountTransaction::query()->withoutGlobalScopes()
            ->where('account_id', $accountId)->where('direction', TransactionDirection::Debit)
            ->whereNull('reversed_at')->whereNull('reversal_of')
            ->get(['id', 'amount'])
            ->mapWithKeys(fn (AccountTransaction $t) => [$t->id => Money::of($t->amount)->minus(Money::of($allocated[$t->id] ?? 0))])
            ->filter(fn (BrickMoney $v) => $v->isPositive())
            ->map(fn (BrickMoney $v) => $v->getAmount())
            ->all();
    }

    /**
     * Transaction içinde: hesabın satırını kilitler. Aynı hesabın cari yazımları
     * (hareket, ters kayıt, dağıtım) sıraya girer; denetle-sonra-yaz adımları yarışmaz.
     */
    public function lockAccount(string $accountId): void
    {
        Account::query()->whereKey($accountId)->lockForUpdate()->value('id');
    }

    /**
     * Kaynağın ters kaydı yapılmamış hareketi (ör. siparişin borcu).
     */
    public function effectiveFor(Model $source, TransactionType $type): ?AccountTransaction
    {
        return AccountTransaction::query()->withoutGlobalScopes()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('type', $type)
            ->whereNull('reversed_at')
            ->whereNull('reversal_of')
            ->first();
    }

    /**
     * Sipariş para birimindeki tutarın, siparişin kendi kurlarıyla ana birim karşılığı.
     */
    public function orderAmount(Order $order, BigNumber|int|string|null $amount): BigDecimal
    {
        if ($order->currency_code === Money::baseCode()) {
            return Money::round($amount);
        }

        $tlRate = $order->currency_code === 'TRY' ? '1' : $order->exchange_rate;
        $baseRate = Money::baseCode() === 'TRY' ? BigDecimal::one() : Money::decimal($order->base_exchange_rate);

        return Money::convertWithRates($amount, $tlRate, $baseRate->isPositive() ? $baseRate : Money::baseRate());
    }

    private static function date(DateTimeInterface|string|null $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::today();
        }

        return ($value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value))->startOfDay();
    }
}
