<?php

declare(strict_types=1);

use App\Enums\Shop\ChequeStatus;
use App\Enums\Shop\CustomerType;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Exceptions\Shop\ShopException;
use App\Models\AccountTransaction;
use App\Models\Cheque;
use App\Models\DealerGroup;
use App\Models\User;
use App\Services\Shop\AgingReport;
use App\Services\Shop\ChequeService;
use App\Services\Shop\CollectionService;
use App\Services\Shop\CreditService;
use App\Services\Shop\LedgerService;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->account = $this->member->account;
    $group = DealerGroup::query()->create(['name' => 'Vadeli', 'discount_rate' => 0, 'payment_terms' => 'credit', 'payment_term_days' => 30, 'over_limit_action' => 'approval', 'overdue_action' => 'approval']);
    $this->account->update(['type' => CustomerType::Dealer, 'dealer_group_id' => $group->id]);

    $this->cheque = fn (array $extra = []) => app(CollectionService::class)->collect($this->account->fresh(), $this->admin, [
        'type' => 'cheque', 'amount' => 500, 'serial_no' => 'C-'.random_int(1000, 9999), 'bank_name' => 'Ziraat',
        'drawer' => 'Bayi Ltd', 'due_date' => now()->addDays(20)->toDateString(), ...$extra,
    ]);
});

it('keeps cheques hidden and unusable until enabled in the ledger settings', function () {
    expect(fn () => ($this->cheque)())->toThrow(ShopException::class);

    $this->actingAs($this->admin)->get(route('panel.shop.cheques.index'))->assertNotFound();
    $this->actingAs($this->admin)->get(route('panel.members.show', $this->member->id))
        ->assertInertia(fn (Assert $page) => $page->where('collectionTypes', fn ($types) => ! collect($types)->contains('value', 'cheque')));

    ShopSettings::save(['cheques_enabled' => '1']);

    $this->actingAs($this->admin)->get(route('panel.members.show', $this->member->id))
        ->assertInertia(fn (Assert $page) => $page->where('collectionTypes', fn ($types) => collect($types)->contains('value', 'cheque')));
    $this->actingAs($this->admin)->get(route('panel.shop.cheques.index'))->assertOk();
});

it('records a cheque collection in the portfolio, counts it in the risk and reverses the credit when it bounces', function () {
    ShopSettings::save(['cheques_enabled' => '1']);
    $ledger = app(LedgerService::class);

    $payment = ($this->cheque)();
    $cheque = Cheque::query()->sole();

    expect($cheque->status)->toBe(ChequeStatus::Portfolio)
        ->and($cheque->payment_id)->toBe($payment->id)
        ->and($ledger->balance($this->account->id))->toEqualDecimal('-500.0')
        // Portföydeki çek riskte: bakiye −500 + çek 500 = 0.
        ->and(app(CreditService::class)->risk($this->account->fresh()))->toEqualDecimal('0.0');

    // Elle iptal edilemez; portföyden yönetilir.
    $credit = AccountTransaction::query()->where('source_id', $payment->id)->sole();
    expect(fn () => app(CollectionService::class)->reverse($credit, $this->admin))->toThrow(ShopException::class);

    $service = app(ChequeService::class);
    $service->changeStatus($cheque, ChequeStatus::Deposited, $this->admin);
    expect(fn () => $service->changeStatus($cheque->fresh(), ChequeStatus::Endorsed, $this->admin))->toThrow(ShopException::class);

    $service->changeStatus($cheque->fresh(), ChequeStatus::Bounced, $this->admin, note: 'Banka iade etti');

    expect($ledger->balance($this->account->id))->toEqualDecimal('0.0')
        ->and(AccountTransaction::query()->where('type', TransactionType::ChequeBounce)->sole()->direction)->toBe(TransactionDirection::Debit)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and(app(CreditService::class)->risk($this->account->fresh()))->toEqualDecimal('0.0');
});

it('endorses a cheque only with a recipient and lists it through the panel', function () {
    ShopSettings::save(['cheques_enabled' => '1']);
    ($this->cheque)();
    $cheque = Cheque::query()->sole();

    $this->actingAs($this->admin)->post(route('panel.shop.cheques.status', $cheque->id), ['status' => 'endorsed'])->assertRedirect();
    expect($cheque->fresh()->status)->toBe(ChequeStatus::Portfolio);

    $this->actingAs($this->admin)->post(route('panel.shop.cheques.status', $cheque->id), ['status' => 'endorsed', 'endorsed_to' => 'Tedarikçi A.Ş.'])->assertRedirect();
    expect($cheque->fresh()->status)->toBe(ChequeStatus::Endorsed)
        ->and($cheque->fresh()->endorsed_to)->toBe('Tedarikçi A.Ş.');

    $this->actingAs($this->admin)->get(route('panel.shop.cheques.index'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/ledger/cheques')->has('cheques.data', 1)->where('cheques.data.0.next.0.value', 'bounced'));
});

it('ages open debits by their due date', function () {
    $ledger = app(LedgerService::class);
    $ledger->record($this->account, TransactionType::Adjustment, TransactionDirection::Debit, 300, ['date' => now()->subDays(45)->toDateString(), 'description' => 'Eski borç']);
    $ledger->record($this->account, TransactionType::Adjustment, TransactionDirection::Debit, 200, ['date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString()]);
    $ledger->record($this->account, TransactionType::Adjustment, TransactionDirection::Credit, 100, ['date' => now()->toDateString()]);

    $report = app(AgingReport::class)->build();

    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0]['d60'])->toEqualDecimal('200.0')
        ->and($report['rows'][0]['not_due'])->toEqualDecimal('200.0')
        ->and($report['totals']['total'])->toEqualDecimal('400.0');

    $this->actingAs($this->admin)->get(route('panel.shop.ledger.aging'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/ledger/aging')->has('rows', 1));
    $this->actingAs($this->admin)->get(route('panel.shop.ledger.aging-export'))->assertOk()->assertDownload();
});
