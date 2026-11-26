<?php

declare(strict_types=1);

use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\CardCheckout;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Payments\CardDetails;
use App\Support\Payments\CardFormGateway;
use App\Support\Payments\Gateways\CardPreviewGateway;
use App\Support\Payments\HostedCardGateway;
use App\Support\Payments\PaymentRegistry;
use Brick\Math\BigDecimal;
use Database\Seeders\ShopSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

/** Ödemeyi hemen onaylayan sahte kart ekranı sürücüsü (sağlayıcının başarılı dönüşü gibi). */
final class ApprovingCardGateway extends CardFormGateway
{
    public function key(): string
    {
        return 'test_card';
    }

    public function label(): string
    {
        return 'Test Kartı';
    }

    protected function configured(): bool
    {
        return true;
    }

    public function charge(Payment $payment, CardDetails $card, Request $request): Response
    {
        $this->complete($payment, $payment->amount, 1, ['bin' => $card->bin()]);

        return $this->finish($payment);
    }

    public function handleCallback(Request $request): Response
    {
        abort(404);
    }

    public function handleReturn(Request $request): Response
    {
        abort(404);
    }

    public function installments(BigDecimal $amount, string $currency, ?string $bin = null): array
    {
        return ['card' => null, 'rows' => []];
    }

    public function refund(Payment $payment, BigDecimal $amount): void {}
}

/**
 * Kart ekranı (CardFormGateway): ödeme formundan sonra girilen bilgiler, siparişin
 * son hali ve kart formu; önizleme sürücüsü ödeme almaz.
 */
beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);
    Carrier::query()->update(['status' => Status::Passive->value]);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'KRT-1', 'name' => 'Kart Ürünü', 'slug' => 'kart-urunu', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 10, StockMovementType::In);

    // Kart ekranlı yöntem: sipariş açılmaz, bekleyen ödeme kaydı döner.
    $this->startByCard = function (string $method = CardPreviewGateway::KEY): Payment {
        ShopSettings::save(['card_preview_enabled' => true]);
        $cart = Basket::cart();
        $cart->set($this->product->fresh(), 2);

        return CheckoutController::placeOrStartPayment(app(OrderService::class), $cart, [
            'email' => 'musteri@example.com',
            'phone' => '05550000000',
            'billing' => ['type' => 'corporate', 'name' => 'Ayşe Yılmaz', 'company' => 'Örnek Ltd.', 'tax_office' => 'Nilüfer', 'tax_number' => '1234567890', 'address' => 'Fatura Cad. 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'same_address' => false,
            'shipping' => ['name' => 'Mehmet Yılmaz', 'phone' => '05551112233', 'address' => 'Teslimat Sok. 2', 'city' => 'Bursa', 'district' => 'Osmangazi'],
            'payment_method' => $method,
            'customer_note' => 'Kapıya bırakın',
        ], null, $this->try, '127.0.0.1');
    };

    $this->charge = fn (Payment $payment) => URL::temporarySignedRoute('shop.payment.charge', now()->addHour(), ['reference' => $payment->merchant_oid]);

    $this->validCard = [
        'card_holder' => 'Ayşe Yılmaz',
        'card_number' => '4111 1111 1111 1111',
        'card_expiry' => '12/'.substr((string) (now()->year + 2), 2),
        'card_cvv' => '123',
    ];
});

it('offers the card preview method only when it is turned on in sales settings', function () {
    $methods = fn () => array_column(CheckoutController::paymentMethods(null, '100.00', $this->try), 'value');

    expect($methods())->not->toContain(CardPreviewGateway::KEY);

    ShopSettings::save(['card_preview_enabled' => true]);

    expect($methods())->toContain(CardPreviewGateway::KEY);
});

it('saves the card preview switch from sales settings', function () {
    $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();

    $this->actingAs($admin)->put(route('panel.shop.sales-settings.update'), [
        'price_display' => 'gross', 'dealer_price_display' => 'net', 'quote_valid_days' => 30, 'return_days' => 14,
        'card_preview_enabled' => '1',
    ])->assertSessionHasNoErrors();

    expect(ShopSettings::bool('card_preview_enabled'))->toBeTrue();
});

it('opens no order at checkout: the card screen shows what was entered and the order to be placed', function () {
    session()->setPreviousUrl(url('/odeme'));
    $payment = ($this->startByCard)();

    expect($payment->order_id)->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and(Order::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Basket::cart()->isEmpty())->toBeFalse()
        ->and($this->product->fresh()->stock)->toBe(10)
        ->and((string) $payment->amount)->toBe('240.00');

    Notification::assertNothingSent();

    $this->get(HostedCardGateway::payUrl($payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/payment/card')
            ->where('payment.reference', $payment->merchant_oid)
            ->where('payment.amount', '240.00')
            ->where('order.number', null)
            ->where('order.email', 'musteri@example.com')
            ->where('order.billing.company', 'Örnek Ltd.')
            ->where('order.shipping.name', 'Mehmet Yılmaz')
            ->where('order.shipping.district', 'Osmangazi')
            ->where('order.customer_note', 'Kapıya bırakın')
            ->has('order.lines', 1)
            ->where('order.lines.0.quantity', 2)
            ->where('maxInstallment', 1)
            ->whereType('notice', 'string')
            ->where('action', fn (string $url) => str_contains($url, 'signature=')));

    // Önizleme veritabanına hiçbir şey yazmaz.
    expect(Order::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('fills the checkout form again with what was entered when the customer comes back from the card screen', function () {
    ($this->startByCard)();

    $prefill = CheckoutController::prefill(null);

    expect($prefill['email'])->toBe('musteri@example.com')
        ->and($prefill['billing']['company'])->toBe('Örnek Ltd.')
        ->and($prefill['billing']['tax_number'])->toBe('1234567890')
        ->and($prefill['shipping']['name'])->toBe('Mehmet Yılmaz')
        ->and($prefill['shipping']['district'])->toBe('Osmangazi')
        ->and($prefill['billing_same'])->toBeFalse()
        ->and($prefill['payment_method'])->toBe(CardPreviewGateway::KEY)
        ->and($prefill['customer_note'])->toBe('Kapıya bırakın');

    // Ödeme alınıp sipariş açılınca form bilgileri silinir.
    CardCheckout::forgetForm();

    expect(CheckoutController::prefill(null)['email'])->toBe('');
});
it('never charges with the preview driver and never opens an order', function () {
    $payment = ($this->startByCard)();

    $this->from(HostedCardGateway::payUrl($payment))
        ->post(($this->charge)($payment), $this->validCard)
        ->assertRedirect(HostedCardGateway::payUrl($payment))
        ->assertSessionHas('toast.type', 'warning');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Order::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Basket::cart()->isEmpty())->toBeFalse();
});

it('places the order only once the card payment is approved and empties the cart', function () {
    app(PaymentRegistry::class)->add(new ApprovingCardGateway);
    $payment = ($this->startByCard)('test_card');

    $this->post(($this->charge)($payment), $this->validCard)->assertRedirect()->assertSessionHas('toast.type', 'success');

    $order = Order::query()->withoutGlobalScopes()->sole();

    expect($payment->fresh()->order_id)->toBe($order->id)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and((string) $order->grand_total)->toBe('240.00')
        ->and($order->customer_note)->toBe('Kapıya bırakın')
        ->and($order->payments()->count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(8)
        ->and(Basket::cart()->isEmpty())->toBeTrue();
});

it('does not send the card to the provider when the order can no longer be placed as shown', function () {
    app(PaymentRegistry::class)->add(new ApprovingCardGateway);
    session()->setPreviousUrl(url('/odeme'));
    $payment = ($this->startByCard)('test_card');

    $this->product->update(['price' => 150]);

    $this->post(($this->charge)($payment), $this->validCard)
        ->assertRedirect(url('/odeme'))
        ->assertSessionHas('toast.type', 'warning');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Order::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('validates the card and never keeps card data in the session', function () {
    $payment = ($this->startByCard)();
    $charge = ($this->charge)($payment);

    $this->from(HostedCardGateway::payUrl($payment))
        ->post($charge, [...$this->validCard, 'card_expiry' => '01/20'])
        ->assertSessionHasErrors('card_year')
        ->assertSessionMissing('_old_input.card_number')
        ->assertSessionMissing('_old_input.card_cvv')
        ->assertSessionMissing('_old_input.card_expiry');

    // Yazım hatalı kart numarası (Luhn).
    $this->from(HostedCardGateway::payUrl($payment))
        ->post($charge, [...$this->validCard, 'card_number' => '4111 1111 1111 1112'])
        ->assertSessionHasErrors('card_number')
        ->assertSessionMissing('_old_input.card_number')
        ->assertSessionMissing('_old_input.card_cvv')
        ->assertSessionMissing('_old_input.card_expiry');
});

it('rejects an unsigned charge and a charge for a settled payment', function () {
    session()->setPreviousUrl(url('/odeme'));
    $payment = ($this->startByCard)();

    $this->post(route('shop.payment.charge', ['reference' => $payment->merchant_oid]), $this->validCard)->assertForbidden();

    $payment->forceFill(['status' => PaymentStatus::Failed])->save();

    $this->post(($this->charge)($payment), $this->validCard)->assertRedirect(url('/odeme'))->assertSessionHas('toast');
});

it('masks the card number and hides it from dumps', function () {
    $card = new CardDetails('AYŞE YILMAZ', '4111111111111111', 12, 2030, '123');

    expect($card->masked())->toBe('4111 11** **** 1111')
        ->and($card->bin())->toBe('411111')
        ->and(print_r($card, true))->not->toContain('4111111111111111')->not->toContain('123')
        ->and(CardDetails::luhn('4111111111111111'))->toBeTrue()
        ->and(CardDetails::luhn('4111111111111112'))->toBeFalse();
});
