<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Payments\CardDetails;
use App\Support\Payments\Gateways\CardPreviewGateway;
use App\Support\Payments\HostedCardGateway;
use Database\Seeders\ShopSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->placeByCard = function (): Order {
        ShopSettings::save(['card_preview_enabled' => true]);
        $cart = Basket::cart();
        $cart->set($this->product->fresh(), 2);

        return app(OrderService::class)->placeFromCart($cart, [
            'email' => 'musteri@example.com',
            'phone' => '05550000000',
            'billing' => ['type' => 'corporate', 'name' => 'Ayşe Yılmaz', 'company' => 'Örnek Ltd.', 'tax_office' => 'Nilüfer', 'tax_number' => '1234567890', 'address' => 'Fatura Cad. 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'same_address' => false,
            'shipping' => ['name' => 'Mehmet Yılmaz', 'phone' => '05551112233', 'address' => 'Teslimat Sok. 2', 'city' => 'Bursa', 'district' => 'Osmangazi'],
            'payment_method' => CardPreviewGateway::KEY,
            'customer_note' => 'Kapıya bırakın',
        ], null, $this->try);
    };

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

it('sends the customer from checkout to the card screen showing what was entered and the order', function () {
    $order = ($this->placeByCard)();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($order->paymentStart->redirectUrl)->toBe(HostedCardGateway::payUrl($payment));

    $this->get(HostedCardGateway::payUrl($payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shop/payment/card')
            ->where('payment.reference', $payment->merchant_oid)
            ->where('payment.amount', (string) $order->grand_total)
            ->where('order.number', $order->number)
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
});

it('never charges with the preview driver and keeps the order waiting for payment', function () {
    $order = ($this->placeByCard)();
    $payment = $order->payments()->sole();
    $charge = URL::temporarySignedRoute('shop.payment.charge', now()->addHour(), ['reference' => $payment->merchant_oid]);

    $this->from(HostedCardGateway::payUrl($payment))
        ->post($charge, $this->validCard)
        ->assertRedirect(HostedCardGateway::payUrl($payment))
        ->assertSessionHas('toast.type', 'warning');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('validates the card and never keeps card data in the session', function () {
    $order = ($this->placeByCard)();
    $payment = $order->payments()->sole();
    $charge = URL::temporarySignedRoute('shop.payment.charge', now()->addHour(), ['reference' => $payment->merchant_oid]);

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
    $order = ($this->placeByCard)();
    $payment = $order->payments()->sole();

    $this->post(route('shop.payment.charge', ['reference' => $payment->merchant_oid]), $this->validCard)->assertForbidden();

    app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Cancelled, [], User::query()->where('email', 'admin@admin.com')->firstOrFail());
    $charge = URL::temporarySignedRoute('shop.payment.charge', now()->addHour(), ['reference' => $payment->merchant_oid]);

    $this->post($charge, $this->validCard)->assertRedirect($order->customerUrl())->assertSessionHas('toast');
});

it('masks the card number and hides it from dumps', function () {
    $card = new CardDetails('AYŞE YILMAZ', '4111111111111111', 12, 2030, '123');

    expect($card->masked())->toBe('4111 11** **** 1111')
        ->and($card->bin())->toBe('411111')
        ->and(print_r($card, true))->not->toContain('4111111111111111')->not->toContain('123')
        ->and(CardDetails::luhn('4111111111111111'))->toBeTrue()
        ->and(CardDetails::luhn('4111111111111112'))->toBeFalse();
});
