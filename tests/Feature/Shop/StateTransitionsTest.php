<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentNotificationStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\QuoteStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Shop\RefundStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Shop\StockTracking;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Enums\Status;
use App\Enums\UserStatus;
use App\Events\Shop\UnappliedPaymentReceivedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\AccountTransaction;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Panel\Settings\Roles\RoleService;
use App\Services\Panel\Settings\User\UserService;
use App\Services\Panel\Shop\ProductService;
use App\Services\Shop\Basket;
use App\Services\Shop\ExchangeRateService;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Services\Shop\PaymentRefundService;
use App\Services\Shop\QuoteService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Modules\ModuleState;
use App\Support\Payments\HostedCardGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\RefundsPayments;
use Database\Seeders\ShopSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Durum makinelerinin geçiş kuralları ve yarış korumaları (açık modelleme).
 */
beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);

    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    Carrier::query()->update(['status' => Status::Passive->value]);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->main = Warehouse::query()->where('code', 'DEPO')->first();
    $this->admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();

    $this->product = Product::create([
        'sku' => 'ST-1', 'name' => 'Durum Ürünü', 'slug' => 'durum-urunu', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, $this->main, 10, StockMovementType::In);

    $this->place = function (array $extra = []): Order {
        $cart = Basket::cart();
        $cart->set($this->product->fresh(), 1);

        return app(OrderService::class)->placeFromCart($cart, [...stateCheckoutData(), ...$extra], null, $this->try);
    };
});

function stateCheckoutData(): array
{
    return [
        'email' => 'musteri@example.com',
        'phone' => '05550000000',
        'billing' => ['type' => 'individual', 'name' => 'Ayşe Yılmaz', 'address' => 'Adres 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => true,
        'payment_method' => 'bank_transfer',
    ];
}

it('enforces the order transition map in the service, not only in the form', function () {
    $order = ($this->place)();
    $orders = app(OrderService::class);

    // Ödeme bekleyen sipariş doğrudan kargoya verilemez.
    expect(fn () => $orders->changeStatus($order, OrderStatus::Shipped, [], $this->admin))->toThrow(ShopException::class);

    $orders->changeStatus($order->fresh(), OrderStatus::Processing, [], $this->admin);
    $orders->changeStatus($order->fresh(), OrderStatus::Shipped, [], $this->admin);

    // Kargoya verilmiş sipariş iptal edilmez: geri dönüş iade talebiyle.
    expect(fn () => $orders->changeStatus($order->fresh(), OrderStatus::Cancelled, [], $this->admin))
        ->toThrow(ShopException::class, 'iade talebi');
});

it('does not fail an order that the admin already moved forward when the payment times out', function () {
    $order = ($this->place)();
    $orders = app(OrderService::class);
    $orders->changeStatus($order, OrderStatus::Processing, [], $this->admin);
    $stockBefore = $this->product->fresh()->stock;

    $orders->markPaymentFailed($order->fresh(), 'Ödeme zaman aşımına uğradı.');

    expect($order->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($order->fresh()->stock_deducted)->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe($stockBefore);
});

it('records a late provider payment for a cancelled order without reopening it', function () {
    Event::fake([UnappliedPaymentReceivedEvent::class]);
    $order = ($this->place)();
    $orders = app(OrderService::class);
    $orders->changeStatus($order, OrderStatus::Cancelled, [], $this->admin);
    $stock = $this->product->fresh()->stock;

    $payment = $order->payments()->create([
        'method' => 'iyzico', 'provider' => 'iyzico', 'merchant_oid' => Payment::newReference('K'),
        'amount' => $order->grand_total, 'currency_code' => 'TRY', 'status' => PaymentStatus::Pending,
    ]);

    $orders->markPaid($order->fresh(), null, 'Kartla ödendi.', $payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->stock_deducted)->toBeFalse()
        ->and($this->product->fresh()->stock)->toBe($stock);

    Event::assertDispatched(UnappliedPaymentReceivedEvent::class);
});

it('does not leave a second card payment pending when the order was already paid', function () {
    Event::fake([UnappliedPaymentReceivedEvent::class]);
    $order = ($this->place)();
    $orders = app(OrderService::class);
    $orders->markPaid($order, $this->admin);

    $late = $order->payments()->create([
        'method' => 'iyzico', 'provider' => 'iyzico', 'merchant_oid' => Payment::newReference('K'),
        'amount' => $order->grand_total, 'currency_code' => 'TRY', 'status' => PaymentStatus::Pending,
    ]);

    $orders->markPaid($order->fresh(), null, 'Kartla ödendi.', $late);

    expect($late->fresh()->status)->toBe(PaymentStatus::Paid);
    Event::assertDispatched(UnappliedPaymentReceivedEvent::class);
});

it('places an order only once for the same checkout token', function () {
    ($this->place)(['checkout_token' => 'tek-kullanim-1']);

    expect(fn () => ($this->place)(['checkout_token' => 'tek-kullanim-1']))->toThrow(ShopException::class, 'zaten gönderildi')
        ->and(Order::query()->count())->toBe(1);
});

it('releases the checkout token when the order could not be placed', function () {
    expect(fn () => ($this->place)(['checkout_token' => 'tek-kullanim-2', 'payment_method' => 'yok']))->toThrow(ShopException::class);

    ($this->place)(['checkout_token' => 'tek-kullanim-2']);

    expect(Order::query()->count())->toBe(1);
});

it('refuses an order whose total changed since the customer saw it, then accepts the resubmission', function () {
    expect(fn () => ($this->place)(['expected_total' => '1.00']))->toThrow(ShopException::class, 'Sipariş tutarı değişti')
        ->and(Order::query()->count())->toBe(0);

    // Yeni tutarı görmüş müşteri aynı formu yeniden gönderir.
    ($this->place)(['expected_total' => '1.00']);

    expect(Order::query()->count())->toBe(1);
});

it('reverses a ledger entry only once even from a stale copy', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $ledger = app(LedgerService::class);
    $entry = $ledger->record($member->account_id, TransactionType::Adjustment, TransactionDirection::Debit, 100);
    $stale = AccountTransaction::query()->findOrFail($entry->id);

    $ledger->reverse($entry);

    expect(fn () => $ledger->reverse($stale))->toThrow(ShopException::class)
        ->and(AccountTransaction::query()->whereNotNull('reversal_of')->count())->toBe(1);
});

it('requires resending a sent quote when its prices change', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $quotes = app(QuoteService::class);
    $payload = [
        'user_id' => $member->id, 'currency_code' => 'TRY',
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => '80', 'tax_rate' => '20']],
    ];
    $quote = $quotes->save(null, $payload, $this->admin);
    $quotes->send($quote, $this->admin);

    $changed = [...$payload, 'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => '70', 'tax_rate' => '20']]];

    expect(fn () => $quotes->save($quote->fresh(), $changed, $this->admin))->toThrow(ShopException::class, 'yeniden gönderilmelidir');

    $quotes->save($quote->fresh(), $changed, $this->admin, resend: true);
    $quotes->send($quote->fresh(), $this->admin);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->fresh()->grand_total)->toEqualDecimal('168.0');
});

it('does not reopen a cancelled quote order whose quote was ordered again', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $quotes = app(QuoteService::class);
    $orders = app(OrderService::class);
    $quote = $quotes->save(null, [
        'user_id' => $member->id, 'currency_code' => 'TRY',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '80', 'tax_rate' => '20']],
    ], $this->admin);
    $quotes->send($quote, $this->admin);
    $quotes->approve($quote->fresh(), $member);

    $first = $orders->placeFromQuote($quote->fresh(), stateCheckoutData(), $member);
    $orders->changeStatus($first, OrderStatus::Cancelled, [], $this->admin);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Approved);

    $orders->placeFromQuote($quote->fresh(), stateCheckoutData(), $member);

    // İlk sipariş yeniden açılırsa aynı tekliften iki sipariş olurdu.
    expect(fn () => $orders->changeStatus($first->fresh(), OrderStatus::Processing, [], $this->admin))
        ->toThrow(ShopException::class, 'yeniden açılamaz');
});

it('keeps the tracking mode while the product has stock', function () {
    $data = [
        'name' => $this->product->name, 'sku' => $this->product->sku, 'price' => '100', 'currency_id' => $this->try->id,
        'tax_rate' => '20', 'sale_mode' => 'cart', 'tracking' => StockTracking::Serial->value, 'min_qty' => 1, 'status' => 'active',
    ];

    expect(fn () => app(ProductService::class)->update($this->product->fresh(), $data, $this->admin))
        ->toThrow(ShopException::class, 'takip türü');
});

it('does not let an admin lock everyone out', function () {
    $panelAdmin = User::query()->where('email', 'panel@admin.com')->firstOrFail();
    $users = app(UserService::class);

    // Kendini pasifleştiremez; Super Admin'i yalnız Super Admin pasifleştirir.
    expect(fn () => $users->updateStatus($panelAdmin, $panelAdmin, UserStatus::Passive))->toThrow(ValidationException::class)
        ->and(fn () => $users->updateStatus($this->admin, $panelAdmin, UserStatus::Passive))->toThrow(ValidationException::class)
        // Son aktif Super Admin pasifleştirilemez.
        ->and(fn () => $users->updateStatus($this->admin, $this->admin, UserStatus::Passive))->toThrow(ValidationException::class)
        // Super Admin'in rolü panelden düşürülemez.
        ->and(fn () => app(RoleService::class)->assignToUser($this->admin, $panelAdmin, 'Admin'))->toThrow(ValidationException::class);

    expect($this->admin->fresh()->status)->toBe(UserStatus::Active)
        ->and($this->admin->fresh()->hasRole(RoleService::SUPER_ADMIN))->toBeTrue();
});

it('refuses the login of a passive user before the login completes', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $member->forceFill(['status' => UserStatus::Passive, 'password' => 'password', 'last_login_at' => null])->save();

    $this->post(route('login.store'), ['email' => 'user@user.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($member->fresh()->last_login_at)->toBeNull();
});

it('updates the module state file atomically and leaves it untouched when a check fails', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'modules-'.uniqid().'.json';
    $state = new ModuleState($path);

    $state->update(fn (array $disabled) => [...$disabled, 'vitrin' => 'Vitrin\\Provider']);

    expect(fn () => $state->update(function (array $disabled): array {
        throw new RuntimeException('bağımlılık');
    }))->toThrow(RuntimeException::class);

    expect((new ModuleState($path))->disabled())->toBe(['vitrin' => 'Vitrin\\Provider']);

    @unlink($path);
    @unlink($path.'.lock');
});

it('keeps the TL value of a non-TRY base currency when rates are saved', function () {
    $usd = Currency::query()->where('code', 'USD')->first();
    $usd->update(['exchange_rate' => 40, 'rate_source' => RateSource::Manual]);
    Setting::query()->updateOrCreate(['key' => 'default_currency_id'], ['value' => $usd->id, 'group' => 'defaults']);
    Setting::flushCache();

    app(ExchangeRateService::class)->saveManual([$usd->id => ['exchange_rate' => '40', 'rate_source' => 'manual']], $this->admin);

    expect($usd->fresh()->exchange_rate)->toEqualDecimal('40');
});

it('releases the stock of a bank transfer order whose reservation expired', function () {
    $order = ($this->place)();
    $order->forceFill(['created_at' => now()->subDays(4)])->saveQuietly();
    $fresh = ($this->place)();
    $stock = $this->product->fresh()->stock;

    $this->artisan('shop:release-stock-reservations')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($order->fresh()->stock_deducted)->toBeFalse()
        ->and($fresh->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($this->product->fresh()->stock)->toBe($stock + 1);
});

it('keeps a reservation while a bank transfer notice awaits review, and when the period is off', function () {
    $order = ($this->place)();
    $order->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    ShopSettings::save(['stock_reservation_days' => '0']);
    $this->artisan('shop:release-stock-reservations')->assertSuccessful();
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    ShopSettings::save(['stock_reservation_days' => '3']);
    $account = User::query()->where('email', 'user@user.com')->firstOrFail()->account_id;
    PaymentNotification::query()->create([
        'account_id' => $account, 'order_id' => $order->id, 'amount' => $order->grand_total,
        'currency_code' => 'TRY', 'transfer_date' => now()->toDateString(), 'status' => PaymentNotificationStatus::Pending,
    ]);
    $this->artisan('shop:release-stock-reservations')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->stock_deducted)->toBeTrue();
});

it('cancels an on-account order left awaiting approval past the reservation period', function () {
    $order = ($this->place)();
    $order->forceFill(['status' => OrderStatus::AwaitingApproval, 'payment_method' => 'on_account', 'created_at' => now()->subDays(5)])->saveQuietly();

    $this->artisan('shop:release-stock-reservations')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancelled_at)->not->toBeNull()
        ->and($order->fresh()->stock_deducted)->toBeFalse();
});

it('lets an admin settle a refund left pending, but not while it may still be in flight', function () {
    $order = ($this->place)();
    $payment = $order->payments()->create([
        'method' => 'iyzico', 'provider' => 'iyzico', 'merchant_oid' => Payment::newReference('K'),
        'amount' => $order->grand_total, 'currency_code' => 'TRY', 'status' => PaymentStatus::Pending,
    ]);
    app(OrderService::class)->markPaid($order->fresh(), null, 'Kartla ödendi.', $payment);

    $stuck = $payment->refunds()->create(['amount' => '10.00', 'currency_code' => 'TRY', 'status' => RefundStatus::Pending, 'user_id' => $this->admin->id]);
    $refunds = app(PaymentRefundService::class);

    // Yeni: sağlayıcı çağrısı sürüyor olabilir.
    expect(fn () => $refunds->abandon($stuck, $this->admin))->toThrow(ShopException::class);

    $stuck->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();
    $refunds->abandon($stuck->fresh(), $this->admin);

    expect($stuck->fresh()->status)->toBe(RefundStatus::Failed)
        ->and($payment->fresh()->refundedAmount())->toBeMoney('0.00');
    expect(fn () => $refunds->confirm($stuck->fresh(), $this->admin))->toThrow(ShopException::class, 'sonuçlanmış');

    $done = $payment->refunds()->create(['amount' => $payment->amount, 'currency_code' => 'TRY', 'status' => RefundStatus::Pending, 'user_id' => $this->admin->id]);
    $done->forceFill(['created_at' => now()->subMinutes(10)])->saveQuietly();

    $this->actingAs($this->admin)
        ->post(route('panel.shop.payments.refunds.confirm', $done->id))
        ->assertSessionHasNoErrors();

    expect($done->fresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

function stateApprovedQuote(Product $product, User $admin, User $member): Quote
{
    $quotes = app(QuoteService::class);
    $quote = $quotes->save(null, [
        'user_id' => $member->id, 'currency_code' => 'TRY',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '80', 'tax_rate' => '20']],
    ], $admin);
    $quotes->send($quote, $admin);
    $quotes->approve($quote->fresh(), $member);

    return $quote->fresh();
}

it('does not cancel a once shipped order by moving it back first', function () {
    $order = ($this->place)();
    $orders = app(OrderService::class);

    $orders->changeStatus($order, OrderStatus::Processing, [], $this->admin);
    $orders->changeStatus($order->fresh(), OrderStatus::Shipped, [], $this->admin);
    $orders->changeStatus($order->fresh(), OrderStatus::Processing, [], $this->admin);

    expect(fn () => $orders->changeStatus($order->fresh(), OrderStatus::Cancelled, [], $this->admin))
        ->toThrow(ShopException::class, 'iade talebi');
});

it('makes the quote payable again when the payment of its order fails', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $orders = app(OrderService::class);
    $quote = stateApprovedQuote($this->product, $this->admin, $member);

    $first = $orders->placeFromQuote($quote, stateCheckoutData(), $member);
    $orders->markPaymentFailed($first, 'Kart reddedildi');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Approved)
        ->and($quote->fresh()->order_id)->toBeNull();

    $orders->placeFromQuote($quote->fresh(), stateCheckoutData(), $member);

    // Teklif yeni siparişe dönüştü: başarısız ilk sipariş artık ödendi yapılamaz.
    expect(fn () => $orders->markPaid($first->fresh(), $this->admin))->toThrow(ShopException::class, 'yeniden açılamaz');
});

it('links the quote back when an admin marks its failed order as paid', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $orders = app(OrderService::class);
    $quote = stateApprovedQuote($this->product, $this->admin, $member);

    $order = $orders->placeFromQuote($quote, stateCheckoutData(), $member);
    $orders->markPaymentFailed($order, 'Kart reddedildi');
    $orders->markPaid($order->fresh(), $this->admin);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Ordered)
        ->and($quote->fresh()->order_id)->toBe($order->id);
});

it('refuses a manual refund status after a card refund on the payment', function () {
    $order = ($this->place)();
    $orders = app(OrderService::class);
    $orders->markPaid($order, $this->admin);

    $payment = $order->payments()->where('status', PaymentStatus::Paid)->firstOrFail();
    $payment->refunds()->create(['amount' => '10.00', 'currency_code' => 'TRY', 'status' => RefundStatus::Succeeded]);

    expect(fn () => $orders->updatePaymentStatus($order->fresh(), PaymentStatus::Refunded, $this->admin))
        ->toThrow(ShopException::class, 'kart iadesi');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('ignores a repeated provider success for a refunded payment', function () {
    $order = ($this->place)();
    app(OrderService::class)->markPaid($order, $this->admin);

    $payment = $order->payments()->where('status', PaymentStatus::Paid)->firstOrFail();
    $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
    $order->fresh()->forceFill(['payment_status' => PaymentStatus::Refunded])->save();

    $gateway = Mockery::mock(HostedCardGateway::class)->makePartial();
    (fn () => $this->complete($payment, (string) $payment->amount, 1, []))->call($gateway);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

it('keeps a refund pending when the provider call times out', function () {
    $order = ($this->place)();
    app(OrderService::class)->markPaid($order, $this->admin);
    $payment = $order->payments()->where('status', PaymentStatus::Paid)->firstOrFail();

    $gateway = Mockery::mock(PaymentGateway::class, RefundsPayments::class);
    $gateway->shouldReceive('key')->andReturn('test_card');
    $gateway->shouldReceive('refund')->andThrow(new ConnectionException('zaman aşımı'));
    app(PaymentRegistry::class)->add($gateway);
    $payment->forceFill(['method' => 'test_card'])->save();

    expect(fn () => app(PaymentRefundService::class)->refund($payment, '10.00', $this->admin))
        ->toThrow(ShopException::class, 'Sağlayıcı panelinden');
    expect($payment->refunds()->sole()->status)->toBe(RefundStatus::Pending);
});

it('does not open the provider page for a cancelled order', function () {
    $order = ($this->place)();
    $payment = $order->payments()->create([
        'method' => 'test_card', 'provider' => 'test_card', 'merchant_oid' => 'K-TEST-1',
        'amount' => $order->grand_total, 'currency_code' => 'TRY', 'status' => PaymentStatus::Pending,
    ]);

    $gateway = Mockery::mock(HostedCardGateway::class)->makePartial();
    $gateway->shouldReceive('key')->andReturn('test_card');
    $gateway->shouldReceive('renderPayment')->once()->andReturn(response('saglayici'));
    app(PaymentRegistry::class)->add($gateway);

    $this->get(HostedCardGateway::payUrl($payment))->assertOk()->assertSee('saglayici');

    app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Cancelled, [], $this->admin);

    $this->get(HostedCardGateway::payUrl($payment))->assertRedirect()->assertSessionHas('toast.type', 'warning');
});

it('does not put the payment of a failed order back to pending', function () {
    $order = ($this->place)();
    $orders = app(OrderService::class);
    $orders->markPaymentFailed($order, 'Kart reddedildi');

    expect(fn () => $orders->updatePaymentStatus($order->fresh(), PaymentStatus::Pending, $this->admin))
        ->toThrow(ShopException::class, 'Başarısız siparişin');
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Failed);

    // Doğrudan "Ödendi" yapılabilir: sipariş hazırlanmaya geçer.
    $orders->markPaid($order->fresh(), $this->admin);
    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});
