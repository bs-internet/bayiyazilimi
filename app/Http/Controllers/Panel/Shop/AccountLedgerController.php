<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\CollectionType;
use App\Enums\Shop\CustomerType;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\Shop\AgingReport;
use App\Services\Shop\CollectionService;
use App\Services\Shop\LedgerExporter;
use App\Support\Ledger\LedgerPresenter;
use App\Support\Money;
use App\Support\Pagination;
use App\Support\Payments\PaymentMethods;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panel › Cari: cari hesaplar (bakiye listesi), tahsilatlar, elle kayıt
 * (tahsilat, dekont, açılış bakiyesi), kayıt iptali ve ekstre (Excel / yazdır).
 * Müşterinin cari sekmesi müşteri detayındadır (MembersController::show).
 */
class AccountLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'side', 'type']);
        $balanceSql = "(select coalesce(sum(case when direction = 'debit' then amount else -amount end), 0) from account_transactions where account_transactions.account_id = accounts.id)";
        $escaped = addcslashes((string) ($filters['q'] ?? ''), '%_\\');

        $accounts = Account::query()
            ->select('accounts.*')
            ->selectRaw("{$balanceSql} as balance")
            ->selectRaw('(select max(transaction_date) from account_transactions where account_transactions.account_id = accounts.id) as last_transaction_at')
            ->with(['dealerGroup:id,name', 'users' => fn ($q) => $q->where('user_type', UserType::Member->value)->select('id', 'account_id', 'name')])
            ->when(($filters['type'] ?? '') !== '', fn ($q) => $q->where('type', $filters['type']))
            ->when($escaped !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$escaped}%")
                ->orWhere('code', 'like', "%{$escaped}%")
                ->orWhereHas('users', fn ($u) => $u->where('name', 'like', "%{$escaped}%")->orWhere('email', 'like', "%{$escaped}%"))))
            ->when(($filters['side'] ?? '') === 'debit', fn ($q) => $q->whereRaw("{$balanceSql} > 0.004"))
            ->when(($filters['side'] ?? '') === 'credit', fn ($q) => $q->whereRaw("{$balanceSql} < -0.004"))
            ->when(($filters['side'] ?? '') === '', fn ($q) => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('account_transactions')->whereColumn('account_transactions.account_id', 'accounts.id')))
            ->orderByRaw("abs({$balanceSql}) desc")
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        $totals = AccountTransaction::query()
            ->selectRaw("coalesce(sum(case when direction = 'debit' then amount else -amount end), 0) as net")
            ->toBase()->first();

        // Toplam alacak: borçlu carilerin bakiyeleri toplamı.
        $receivable = Money::round(DB::query()->fromSub(
            AccountTransaction::query()->selectRaw("account_id, sum(case when direction = 'debit' then amount else -amount end) as b")->groupBy('account_id'),
            'balances',
        )->where('b', '>', 0)->sum('b'));

        return Inertia::render('panel/shop/ledger/index', [
            'accounts' => $accounts->through(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'title' => $a->title ?? $a->users->first()?->name ?? $a->code,
                'type' => $a->type?->value,
                'type_label' => $a->type?->label(),
                'group' => $a->dealerGroup?->name,
                'member_id' => $a->users->first()?->id,
                'balance' => LedgerPresenter::side(Money::round($a->getAttribute('balance'))),
                'last_transaction_at' => $a->getAttribute('last_transaction_at'),
            ]),
            'filters' => $filters,
            'types' => CustomerType::options(),
            'totals' => [
                'receivable' => Money::format($receivable),
                'net' => LedgerPresenter::side(Money::round($totals->net ?? 0)),
            ],
        ]);
    }

    /**
     * Tahsilatlar: ödenmiş tüm ödeme kayıtları (sipariş ödemeleri + cari tahsilatlar).
     */
    public function collections(Request $request): Response
    {
        $filters = $request->only(['method', 'from', 'to', 'q']);
        $escaped = addcslashes((string) ($filters['q'] ?? ''), '%_\\');

        $query = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('account_id')
            ->with(['account:id,title,code', 'order:id,number', 'user:id,name', 'account.users' => fn ($q) => $q->select('id', 'account_id', 'name')])
            ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('method', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))
            ->when($escaped !== '', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('title', 'like', "%{$escaped}%")->orWhere('code', 'like', "%{$escaped}%")));

        $payments = (clone $query)->latest('paid_at')->paginate(Pagination::PER_PAGE)->withQueryString();

        return Inertia::render('panel/shop/ledger/collections', [
            'payments' => $payments->through(fn (Payment $p) => [
                'id' => $p->id,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'account' => $p->account ? ['id' => $p->account->id, 'title' => $p->account->title ?? $p->account->code, 'member_id' => $p->account->users->first()?->id] : null,
                'order' => $p->order ? ['id' => $p->order->id, 'number' => $p->order->number] : null,
                'method_label' => $p->methodLabel(),
                'amount' => $p->currency()?->format($p->amount) ?? $p->amount.' '.$p->currency_code,
                'reference' => $p->merchant_oid,
                'note' => $p->note,
                'user' => $p->user?->name,
            ]),
            'filters' => $filters,
            'methods' => PaymentMethods::options(Payment::query()->distinct()->pluck('method')->all()),
        ]);
    }

    /**
     * Yaşlandırma: açık borçların vadesine göre dağılımı.
     */
    public function aging(AgingReport $report): Response
    {
        $data = $report->build();

        return Inertia::render('panel/shop/ledger/aging', [
            'buckets' => AgingReport::buckets(),
            'rows' => array_map(fn (array $row) => [
                ...$row,
                'formatted' => array_map(fn ($key) => Money::format($row[$key]), array_combine([...array_keys(AgingReport::buckets()), 'total'], [...array_keys(AgingReport::buckets()), 'total'])),
            ], $data['rows']),
            'totals' => array_map(fn ($v) => Money::format($v), $data['totals']),
        ]);
    }

    public function agingExport(AgingReport $report): BinaryFileResponse
    {
        return response()->download($report->export(), 'yaslandirma-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function collect(Request $request, Account $account, CollectionService $collections): RedirectResponse
    {
        $instrument = CollectionType::tryFrom((string) $request->input('type'))?->isInstrument() ?? false;

        $data = $request->validate([
            'type' => ['required', Rule::in(array_column(CollectionType::options(), 'value'))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'serial_no' => [$instrument ? 'required' : 'nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'drawer' => ['nullable', 'string', 'max:255'],
            'due_date' => [$instrument ? 'required' : 'nullable', 'date'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'order_ids' => ['nullable', 'array', 'max:200'],
            'order_ids.*' => ['uuid', 'distinct'],
        ], [], [
            'order_ids' => __('siparişler'), 'order_ids.*' => __('sipariş'),
            'type' => __('tahsilat türü'), 'amount' => __('tutar'), 'date' => __('tarih'), 'description' => __('açıklama'),
            'serial_no' => __('seri no'), 'bank_name' => __('banka'), 'branch' => __('şube'), 'drawer' => __('keşideci'), 'due_date' => __('vade'), 'image' => __('görsel'),
        ]);

        $payment = $collections->collect($account, $request->user(), $data, $request->file('image'));

        return back()->with('toast', ['type' => 'success', 'message' => __(':amount tahsilat cariye işlendi.', ['amount' => Money::format($payment->amount)])]);
    }

    /**
     * Borç / alacak dekontu ya da açılış bakiyesi.
     */
    public function adjust(Request $request, Account $account, CollectionService $collections): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::enum(TransactionDirection::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'opening' => ['boolean'],
        ], [], ['direction' => __('yön'), 'amount' => __('tutar'), 'date' => __('tarih'), 'description' => __('açıklama')]);

        $collections->adjust(
            $account,
            $request->user(),
            TransactionDirection::from($data['direction']),
            (string) $data['amount'],
            $data['date'] ?? null,
            $data['description'],
            (bool) ($data['opening'] ?? false),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Cari kayıt eklendi.')]);
    }

    public function reverse(Request $request, AccountTransaction $transaction, CollectionService $collections): RedirectResponse
    {
        $collections->reverse($transaction, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Kayıt iptal edildi (ters kayıt yazıldı).')]);
    }

    public function export(Request $request, Account $account, LedgerExporter $exporter): BinaryFileResponse
    {
        $path = $exporter->export($account, $request->input('from'), $request->input('to'));

        return response()->download($path, 'cari-ekstre-'.Str::slug($account->code).'-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Yazdırılabilir ekstre (tarayıcının yazdır / PDF olarak kaydet'i ile).
     */
    public function print(Request $request, Account $account, LedgerPresenter $presenter): View
    {
        $member = User::query()->where('account_id', $account->id)->where('user_type', UserType::Member->value)->oldest()->first();

        return view('shop.statement', [
            'account' => $account,
            'member' => $member,
            'ledger' => $presenter->forAccount($account, $request->input('from'), $request->input('to')),
        ]);
    }
}
