<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\ReturnStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Shop\CollectionService;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Services\Shop\ReturnService;
use App\Support\Money;
use App\Support\Payments\HostedCardGateway;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->account = $this->member->account;
    $this->ledger = app(LedgerService::class);

    $this->order = fn (array $attributes = []) => Order::query()->create(array_merge([
        'number' => 'S'.random_int(100000, 999999), 'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending, 'payment_method' => 'bank_transfer',
        'user_id' => $this->member->id, 'account_id' => $this->account->id,
        'customer_name' => 'Bayi', 'email' => 'bayi@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Bayi'], 'shipping_address' => ['name' => 'Bayi'],
        'currency_code' => 'TRY', 'exchange_rate' => 1, 'base_exchange_rate' => 1, 'grand_total' => 1200,
    ], $attributes));
});

it('writes the order debit and the payment credit when a transfer is confirmed', function () {
    $order = ($this->order)();

    expect($this->ledger->balance($this->account->id))->toEqualDecimal('0.0');

    app(OrderService::class)->updatePaymentStatus($order, PaymentStatus::Paid, $this->admin);

    $rows = AccountTransaction::query()->where('account_id', $this->account->id)->orderBy('created_at')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('type')->all())->toBe([TransactionType::Order, TransactionType::Payment])
        ->and($rows[0]->direction)->toBe(TransactionDirection::Debit)
        ->and($rows[0]->amount)->toEqualDecimal('1200.0')
        ->and($rows[0]->due_date?->toDateString())->toBe(now()->toDateString())
        ->and($this->ledger->balance($this->account->id))->toEqualDecimal('0.0');

    // İade: ödeme kaydı iade edilince alacak ters kayıtla kapanır, müşteri yeniden borçlu görünür.
    app(OrderService::class)->updatePaymentStatus($order->fresh(), PaymentStatus::Refunded, $this->admin);
    expect($this->ledger->balance($this->account->id))->toEqualDecimal('1200.0');
});

it('debits an order when it is moved to processing and reverses it on cancel', function () {
    $order = ($this->order)(['grand_total' => 500]);

    app(OrderService::class)->changeStatus($order, OrderStatus::Processing, [], $this->admin);
    expect($this->ledger->balance($this->account->id))->toEqualDecimal('500.0');

    // Aynı durum tekrar seçilse de borç bir kez yazılır.
    app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Processing, ['note' => 'Not'], $this->admin);
    expect($this->ledger->balance($this->account->id))->toEqualDecimal('500.0');

    app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Cancelled, [], $this->admin);
    expect($this->ledger->balance($this->account->id))->toEqualDecimal('0.0')
        ->and(AccountTransaction::query()->whereNotNull('reversal_of')->count())->toBe(1);
});

it('converts foreign currency orders to the base currency with the order rate', function () {
    $order = ($this->order)(['currency_code' => 'EUR', 'exchange_rate' => 40, 'grand_total' => 100]);

    app(OrderService::class)->changeStatus($order, OrderStatus::Processing, [], $this->admin);

    $debit = AccountTransaction::query()->where('source_id', $order->id)->sole();
    expect($debit->amount)->toEqualDecimal('4000.0')
        ->and($debit->original_amount)->toEqualDecimal('100.0')
        ->and($debit->original_currency)->toBe('EUR')
        ->and($debit->currency_code)->toBe('TRY');
});

it('credits a completed return and debits the money refund', function () {
    $order = ($this->order)(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid]);
    $this->ledger->recordOrder($order);

    $item = $order->items()->create(['sku' => 'X1', 'name' => 'Ürün', 'quantity' => 10, 'unit_price' => 100, 'tax_rate' => 20, 'tax_amount' => 200, 'line_total' => 1200]);

    $return = ReturnRequest::query()->create([
        'number' => 'I'.random_int(100000, 999999), 'order_id' => $order->id, 'account_id' => $this->account->id,
        'user_id' => $this->member->id, 'status' => ReturnStatus::Received, 'reason' => 'damaged',
    ]);
    $return->items()->create(['order_item_id' => $item->id, 'quantity' => 3]);

    app(ReturnService::class)->changeStatus($return, ReturnStatus::Refunded, $this->admin, ['refund_amount' => '300', 'mark_refunded' => false]);

    expect($this->ledger->balance($this->account->id))->toEqualDecimal('900.0')
        ->and(AccountTransaction::query()->where('type', TransactionType::ReturnCredit)->count())->toBe(1);
});

it('records manual collections and adjustments and reverses only manual entries', function () {
    $collections = app(CollectionService::class);

    $collections->adjust($this->account, $this->admin, TransactionDirection::Debit, 1000, '2026-01-01', 'Devir', opening: true);
    $payment = $collections->collect($this->account, $this->admin, ['type' => 'cash', 'amount' => 400, 'description' => 'Elden']);

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->order_id)->toBeNull()
        ->and($this->ledger->balance($this->account->id))->toEqualDecimal('600.0');

    $credit = AccountTransaction::query()->where('source_id', $payment->id)->sole();
    $collections->reverse($credit, $this->admin);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($this->ledger->balance($this->account->id))->toEqualDecimal('1000.0');

    // Sipariş borcu belgesinden yönetilir.
    $order = ($this->order)(['grand_total' => 50]);
    app(OrderService::class)->changeStatus($order, OrderStatus::Processing, [], $this->admin);
    $debit = AccountTransaction::query()->where('source_id', $order->id)->sole();

    expect(fn () => $collections->reverse($debit, $this->admin))->toThrow(ShopException::class);

    // Çek / senet kapalıyken tahsilat türü olarak kullanılamaz.
    expect(fn () => $collections->collect($this->account, $this->admin, ['type' => 'cheque', 'amount' => 10]))->toThrow(ShopException::class);
});

it('builds a statement with the carried-over balance and keeps ERP entries unique', function () {
    $this->ledger->record($this->account, TransactionType::Adjustment, TransactionDirection::Debit, 100, ['date' => '2026-01-10', 'origin' => 'erp:logo', 'external_ref' => 'F-1']);
    $this->ledger->record($this->account, TransactionType::Adjustment, TransactionDirection::Debit, 100, ['date' => '2026-01-10', 'origin' => 'erp:logo', 'external_ref' => 'F-1']);
    $this->ledger->record($this->account, TransactionType::Adjustment, TransactionDirection::Credit, 30, ['date' => '2026-02-05']);

    $statement = $this->ledger->statement($this->account->id, now()->setDate(2026, 2, 1), null);

    expect(AccountTransaction::query()->count())->toBe(2)
        ->and($statement['opening'])->toEqualDecimal('100.0')
        ->and($statement['closing'])->toEqualDecimal('70.0')
        ->and($statement['rows'])->toHaveCount(1);
});

it('shows the ledger on the member detail and lets the admin collect, export and print', function () {
    $this->actingAs($this->admin)
        ->post(route('panel.shop.ledger.adjust', $this->account->id), ['direction' => 'debit', 'amount' => '250', 'description' => 'Açılış', 'opening' => '1'])
        ->assertRedirect();
    $this->actingAs($this->admin)
        ->post(route('panel.shop.ledger.collect', $this->account->id), ['type' => 'bank_transfer', 'amount' => '100'])
        ->assertRedirect();

    $this->actingAs($this->admin)->get(route('panel.members.show', $this->member->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ledger.summary.side', 'debit')
            ->where('ledger.summary.amount', '150.00')
            ->has('ledger.statement.rows', 2)
            ->where('ledger.statement.rows.0.reversible', true));

    $this->actingAs($this->admin)->get(route('panel.shop.ledger.index'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/ledger/index')->has('accounts.data', 1));
    $this->actingAs($this->admin)->get(route('panel.shop.ledger.collections'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/ledger/collections')->has('payments.data', 1));

    $this->actingAs($this->admin)->get(route('panel.shop.ledger.export', $this->account->id))->assertOk()->assertDownload();
    $this->actingAs($this->admin)->get(route('panel.shop.ledger.print', $this->account->id))->assertOk()->assertSee('Cari Hesap Ekstresi');
});

it('shows the member their own ledger on Cari Hesabım', function () {
    app(CollectionService::class)->adjust($this->account, $this->admin, TransactionDirection::Debit, 75, null, 'Açılış', opening: true);

    $this->actingAs($this->member)->get(route('app.shop.payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/payments/index')
            ->where('ledger.summary.amount', '75.00')
            ->where('ledger.statement.rows.0.reversible', false)
            ->where('ledger.statement.rows.0.user', null));

    expect(Payment::query()->count())->toBe(0)
        ->and(Account::query()->whereKey($this->account->id)->exists())->toBeTrue();
});

it('closes the selected orders first and sends the rest to the oldest debt', function () {
    $orders = app(OrderService::class);
    $older = ($this->order)(['grand_total' => 1000]);
    $newer = ($this->order)(['grand_total' => 500]);
    $orders->changeStatus($older, OrderStatus::Processing, [], $this->admin);
    $orders->changeStatus($newer, OrderStatus::Processing, [], $this->admin);

    expect(collect($this->ledger->openOrders($this->account->id))->pluck('id')->all())->toBe([$older->id, $newer->id]);

    app(CollectionService::class)->collect($this->account, $this->admin, ['type' => 'cash', 'amount' => '600', 'order_ids' => [$newer->id]]);

    expect($newer->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($older->fresh()->payment_status)->toBe(PaymentStatus::Partial)
        ->and($this->ledger->openDebits($this->account->id))->toHaveCount(1);
});

it('leaves collections without a selection on the oldest debt', function () {
    $orders = app(OrderService::class);
    $older = ($this->order)(['grand_total' => 1000]);
    $newer = ($this->order)(['grand_total' => 500]);
    $orders->changeStatus($older, OrderStatus::Processing, [], $this->admin);
    $orders->changeStatus($newer, OrderStatus::Processing, [], $this->admin);

    app(CollectionService::class)->collect($this->account, $this->admin, ['type' => 'cash', 'amount' => '500']);

    expect($older->fresh()->payment_status)->toBe(PaymentStatus::Partial)
        ->and($newer->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('refuses an order of another account in a collection', function () {
    $other = Account::query()->create(['code' => 'DIGER1', 'title' => 'Diğer']);
    $foreign = ($this->order)(['account_id' => $other->id]);

    expect(fn () => app(CollectionService::class)->collect($this->account, $this->admin, ['type' => 'cash', 'amount' => '100', 'order_ids' => [$foreign->id]]))
        ->toThrow(ShopException::class);
    expect(Payment::query()->where('account_id', $this->account->id)->where('provider', 'manual')->exists())->toBeFalse();
});

it('applies the orders chosen for a card balance payment when it is paid', function () {
    $orders = app(OrderService::class);
    $older = ($this->order)(['grand_total' => 1000]);
    $newer = ($this->order)(['grand_total' => 500]);
    $orders->changeStatus($older, OrderStatus::Processing, [], $this->admin);
    $orders->changeStatus($newer, OrderStatus::Processing, [], $this->admin);

    $gateway = Mockery::mock(HostedCardGateway::class)->makePartial();
    $gateway->shouldReceive('key')->andReturn('test_card');
    $gateway->startBalancePayment($this->account, $this->member, Money::round('500'), [$newer->id]);

    $payment = Payment::query()->where('method', 'test_card')->sole();
    (fn () => $this->complete($payment, '500', 1, []))->call($gateway);

    expect($newer->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($older->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});
