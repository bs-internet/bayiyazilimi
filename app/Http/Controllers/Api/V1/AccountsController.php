<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingType;
use App\Enums\Shop\CustomerType;
use App\Enums\Shop\PaymentTerms;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * REST API cari hesaplar: liste / tekil + bakiye ve kredi bilgisi, cari
 * hareketler (accounts:read); hesap açma / güncelleme (accounts:write);
 * dış sistemden cari hareket (ledger:write).
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

    /** Cari hesap (bayi / müşteri) açma (accounts:write). Kod verilmezse üretilir. */
    public function store(Request $request, LedgerService $ledger): JsonResponse
    {
        $data = $this->accountData($request);
        $data['code'] ??= self::newCode();
        $data['type'] ??= CustomerType::Dealer->value;

        $account = Account::query()->create($data);
        $account->load(AccountResource::relations());

        return (new AccountResource($account))->withBalance($ledger->balance($account->id))->response()->setStatusCode(201);
    }

    /** Cari hesap güncelleme (accounts:write): yalnız gönderilen alanlar değişir. */
    public function update(Request $request, Account $account, LedgerService $ledger): AccountResource
    {
        $account->update($this->accountData($request, $account));
        $account->load(AccountResource::relations());

        return (new AccountResource($account))->withBalance($ledger->balance($account->id));
    }

    /** Cari hareketler (accounts:read): tarih sırasıyla, en yeni önce; `from` / `to` ile süzülür. */
    public function transactions(Request $request, Account $account): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $account->transactions()
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('transaction_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('transaction_date', '<=', $to))
            ->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->paginate($this->perPage($filters))->withQueryString();

        return AccountTransactionResource::collection($page)->response();
    }

    /** @return array<string, mixed> */
    private function accountData(Request $request, ?Account $account = null): array
    {
        $sometimes = $account ? 'sometimes' : 'nullable';

        return $request->validate([
            'code' => [$account ? 'prohibited' : 'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('accounts', 'code')],
            'title' => [$account ? 'sometimes' : 'required', 'string', 'max:255'],
            'type' => [$sometimes, Rule::enum(CustomerType::class)],
            'billing_type' => [$sometimes, Rule::enum(BillingType::class)],
            'tax_office' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'identity_number' => ['sometimes', 'nullable', 'string', 'max:11'],
            'invoice_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'dealer_group_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('dealer_groups', 'id')],
            'discount_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms' => ['sometimes', 'nullable', Rule::enum(PaymentTerms::class)],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'payment_term_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
        ], [], ['code' => __('cari kodu'), 'title' => __('ünvan')]);
    }

    private static function newCode(): string
    {
        do {
            $code = 'API'.strtoupper(Str::random(7));
        } while (Account::query()->where('code', $code)->exists());

        return $code;
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
