<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\User;
use App\Services\Shop\LedgerService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * REST API'den gelen cari hareketler (ERP / muhasebe): tahsilat, borç ve
 * alacak dekontu. LedgerService::record() ile `origin = api` + dış sistemin
 * referansı (`external_ref`) yazılır; aynı referans ikinci kez gelirse yeni
 * kayıt açılmaz, mevcut kayıt döner (istek güvenle tekrarlanabilir).
 */
class ExternalLedgerService
{
    public const ORIGIN = 'api';

    /** API türü => [hareket türü, yön]. */
    public const TYPES = [
        'collection' => [TransactionType::Payment, TransactionDirection::Credit],
        'debit' => [TransactionType::Adjustment, TransactionDirection::Debit],
        'credit' => [TransactionType::Adjustment, TransactionDirection::Credit],
    ];

    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array{type: string, amount: string, reference: string, date?: ?string, due_date?: ?string, description?: ?string}  $data
     * @return array{transaction: AccountTransaction, created: bool}
     */
    public function record(Account $account, array $data, User $causer): array
    {
        $reference = trim($data['reference']);

        if (($existing = $this->find($reference)) !== null) {
            return ['transaction' => $this->ensureSameAccount($existing, $account), 'created' => false];
        }

        [$type, $direction] = self::TYPES[$data['type']];

        try {
            $transaction = $this->ledger->record($account, $type, $direction, Money::round($data['amount']), [
                'date' => $data['date'] ?? null,
                'due_date' => $direction === TransactionDirection::Debit ? ($data['due_date'] ?? null) : null,
                'description' => ($data['description'] ?? null) ?: $type->label(),
                'origin' => self::ORIGIN,
                'external_ref' => $reference,
                'user' => $causer,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Aynı referansla eşzamanlı iki istek: ikincisi tekil indekse takılır.
            $existing = $this->find($reference) ?? throw new ShopException(__('Cari hareket yazılamadı; isteği yeniden gönderin.'));

            return ['transaction' => $this->ensureSameAccount($existing, $account), 'created' => false];
        }

        // LedgerService aynı referansı kilit altında yeniden denetler; yarışta mevcut kayıt döner.
        return ['transaction' => $this->ensureSameAccount($transaction, $account), 'created' => $transaction->wasRecentlyCreated];
    }

    private function find(string $reference): ?AccountTransaction
    {
        return AccountTransaction::query()->withoutGlobalScopes()
            ->where('origin', self::ORIGIN)
            ->where('external_ref', $reference)
            ->first();
    }

    private function ensureSameAccount(AccountTransaction $transaction, Account $account): AccountTransaction
    {
        if ($transaction->account_id !== $account->id) {
            throw new ShopException(__('Bu referans başka bir cari hesabın hareketinde kullanılmış.'));
        }

        return $transaction;
    }
}
