<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Payments\PaymentMethods;
use App\Support\Payments\PaymentRegistry;
use Database\Seeders\ShopSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\Payments\FakeCardGateway;

beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);
    Carrier::query()->update(['status' => Status::Passive->value]);
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'PAY-1', 'name' => 'Ödeme Ürünü', 'slug' => 'odeme-urunu', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 10, StockMovementType::In);

    $this->checkout = fn (string $method) => [
        'email' => 'musteri@example.com', 'phone' => '05550000000',
        'billing' => ['type' => 'individual', 'name' => 'Ayşe', 'address' => 'Adres', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => true, 'payment_method' => $method,
    ];
});

it('registers the bank transfer driver and resolves labels with a fallback', function () {
    $registry = app(PaymentRegistry::class);

    expect($registry->has('bank_transfer'))->toBeTrue()
        ->and(PaymentMethods::label('bank_transfer'))->toBe('Havale / EFT')
        ->and(PaymentMethods::label('cash'))->toBe('Nakit')
        ->and(PaymentMethods::label('removed_module'))->toBe('removed_module');

    // Havale kapalıyken sunulmaz.
    ShopSettings::save(['bank_transfer_enabled' => '0']);
    expect(CheckoutController::paymentMethods())->toBe([]);
});

it('lets a module add a payment method through the hook', function () {
    hooks()->action('shop.payment.methods.register', fn (PaymentRegistry $payments) => $payments->add(new FakeCardGateway));

    expect(array_column(CheckoutController::paymentMethods(), 'value'))->toBe(['bank_transfer', 'fake_card']);
});

it('writes a payment record when a bank transfer order is marked paid', function () {
    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 2);
    $order = app(OrderService::class)->placeFromCart($cart, ($this->checkout)('bank_transfer'), null, $this->try);

    expect($order->payments()->count())->toBe(0)
        ->and($order->paymentStart?->redirects())->toBeFalse();

    app(OrderService::class)->updatePaymentStatus($order, PaymentStatus::Paid, User::factory()->admin()->create());

    $payment = $order->payments()->sole();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->method)->toBe('bank_transfer')
        ->and($payment->amount)->toEqualDecimal($order->grand_total)
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing);

    // Ödendi'den iade edilince ödeme kaydı da iade olur.
    app(OrderService::class)->updatePaymentStatus($order->fresh(), PaymentStatus::Refunded, User::factory()->admin()->create());
    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('redirects to the driver payment page and completes the order from the provider callback', function () {
    app(PaymentRegistry::class)->add(new FakeCardGateway);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 1);
    $order = app(OrderService::class)->placeFromCart($cart, ($this->checkout)('fake_card'), null, $this->try);

    expect($order->paymentStart?->redirectUrl)->toStartWith('/test-kart/')
        ->and(CheckoutController::redirectAfterPlaced($order, '/siparis')->getTargetUrl())->toContain('/test-kart/');

    $payment = $order->payments()->sole();

    // Sağlayıcı bildirimi: CSRF dışı, oturumsuz.
    $this->post('/odeme/bildirim/fake_card', ['oid' => $payment->merchant_oid])->assertOk()->assertSee('OK');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing);

    $this->post('/odeme/bildirim/bilinmeyen', [])->assertNotFound();
});

it('expires pending payments after the driver timeout and restores stock', function () {
    app(PaymentRegistry::class)->add(new FakeCardGateway);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 3);
    $order = app(OrderService::class)->placeFromCart($cart, ($this->checkout)('fake_card'), null, $this->try);
    expect($this->product->fresh()->stock)->toBe(7);

    $this->artisan('shop:expire-pending-payments')->assertSuccessful();
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    $this->travel(31)->minutes();
    $this->artisan('shop:expire-pending-payments')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->payments()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('hides a method below its minimum order amount and rejects it on placement', function () {
    ShopSettings::save(['payment_min_amounts' => json_encode(['bank_transfer' => 500])]);

    expect(CheckoutController::paymentMethods(null, '240.00', $this->try))->toBe([])
        ->and(CheckoutController::paymentMethods(null, '600.00', $this->try))->toHaveCount(1);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 2); // 240 TL

    expect(fn () => app(OrderService::class)->placeFromCart($cart, ($this->checkout)('bank_transfer'), null, $this->try))
        ->toThrow(ShopException::class, 'Seçilen ödeme yöntemi şu anda kullanılamıyor.');
});
