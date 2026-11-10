<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Shop\CustomerType;
use App\Http\Controllers\Api\V1\Concerns\ListsForSync;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAccountTransactionRequest;
use App\Http\Resources\Api\V1\AccountCollection;
use App\Http\Resources\Api\V1\AccountResource;
use App\Http\Resources\Api\V1\AccountTransactionResource;
use App\Models\Account;
use App\Services\Api\ExternalLedgerService;
use App\Services\Shop\CreditService;
use App\Services\Shop\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * REST API cari hesaplar: liste / tekil + bakiye ve kredi bilgisi
 * (accounts:read); dış sistemden cari hareket (ledger:write).
 */
class AccountsController extends Controller
{
    use ListsForSync;

    public function index(Request $request, LedgerService $ledger): AccountCollection
    {
        $filters = $this->listFilters($request, [
            'type' => ['nullable', Rule::enum(CustomerType::class)],
        ]);

        $query = Account::query()
            ->with(AccountResource::relations())
            ->when($filters['type'] ?? null, fn ($q, string $type) => $q->where('type', $type));

        $accounts = $this->applySync($query, $filters)->paginate($this->perPage($filters))->withQueryString();

        return new AccountCollection($accounts, $ledger->balances($accounts->getCollection()->modelKeys()));
    }

    public function show(Account $account, LedgerService $ledger, CreditService $credit): AccountResource
    {
        $account->load(AccountResource::relations());

        return (new AccountResource($account))
            ->withBalance($ledger->balance($account->id))
            ->withCredit([
                'risk' => $credit->risk($account),
                'available' => $credit->available($account),
                'overdue' => $credit->overdue($account),
            ]);
    }

    public function storeTransaction(StoreAccountTransactionRequest $request, Account $account, ExternalLedgerService $service): JsonResponse
    {
        /** @var array{type: string, amount: string, reference: string, date?: ?string, due_date?: ?string, description?: ?string} $data */
        $data = [...$request->validated(), 'amount' => (string) $request->validated('amount')];

        ['transaction' => $transaction, 'created' => $created] = $service->record($account, $data, $request->user());

        return (new AccountTransactionResource($transaction))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }
}
