<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Mail\Shop\OrderReceivedMail;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Shop\NewOrderAdminNotification;
use App\Services\Shop\Basket;
use App\Services\Shop\OrderService;
use App\Services\Shop\ReturnService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Payments\PaymentRegistry;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\Payments\AuditCardGateway;

/** Ödemesi sağlayıcı sayfasında tamamlanan sahte kart sürücüsü. */
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    ShopSettings::save(['order_notification_email' => 'siparis@example.com']);

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->try = Currency::query()->where('code', 'TRY')->firstOrFail();
    $this->warehouse = Warehouse::query()->where('code', 'DEPO')->firstOrFail();
    $this->product = Product::create([
        'sku' => 'AUD-1', 'name' => 'Denetim Ürünü', 'slug' => 'denetim-urunu', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, $this->warehouse, 10, StockMovementType::In);

    $this->place = function (string $method = 'bank_transfer', int $qty = 1): Order {
        $this->actingAs($this->member);
        $cart = Basket::cart();
        $cart->add($this->product->fresh(), $qty);

        return app(OrderService::class)->placeFromCart($cart, [
            'email' => 'bayi@example.com', 'phone' => '05550000000',
            'billing' => ['type' => 'corporate', 'name' => 'Bayi', 'address' => 'Adres', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'same_address' => true, 'payment_method' => $method,
        ], $this->member->fresh(), $this->try);
    };
});

it('gives the Admin role every panel permission at install', function () {
    $admin = Role::findByName('Admin');

    expect($admin->hasPermissionTo('panel.members.index'))->toBeTrue()
        ->and($admin->hasPermissionTo('panel.members.create'))->toBeTrue()
        ->and($admin->hasPermissionTo('panel.settings.users.index'))->toBeTrue()
        ->and($admin->hasPermissionTo('panel.tools.activity'))->toBeTrue()
        ->and($admin->hasPermissionTo('panel.shop.orders.index'))->toBeTrue();
});

it('offers only valid status changes for an order and rejects the rest', function () {
    $order = ($this->place)();
    expect($order->status)->toBe(OrderStatus::Pending);

    $this->actingAs($this->admin)->get(route('panel.shop.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('statuses', OrderStatus::optionsFor([
            OrderStatus::Pending, OrderStatus::Processing, OrderStatus::Cancelled,
        ])));

    // Ödeme Başarısız elle seçilemez (stok ve cari geri alınmaz).
    $this->actingAs($this->admin)->patch(route('panel.shop.orders.status', $order), ['status' => OrderStatus::Failed->value])
        ->assertSessionHasErrors('status');
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    $this->actingAs($this->admin)->patch(route('panel.shop.orders.status', $order), ['status' => OrderStatus::Cancelled->value])
        ->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('emails the store once per order for bank transfer and card payments', function () {
    $transfer = ($this->place)();
    app(OrderService::class)->markPaid($transfer, $this->admin);

    Notification::assertSentOnDemandTimes(NewOrderAdminNotification::class, 1);

    app(PaymentRegistry::class)->add(new AuditCardGateway);
    $card = ($this->place)('audit_card');

    // Kart: sipariş anında değil, ödeme gelince bir kez.
    Notification::assertSentOnDemandTimes(NewOrderAdminNotification::class, 1);

    $payment = $card->payments()->create([
        'account_id' => $card->account_id, 'method' => 'audit_card', 'provider' => 'test',
        'merchant_oid' => Payment::newReference(), 'amount' => $card->grand_total,
        'currency_code' => $card->currency_code, 'status' => PaymentStatus::Pending,
    ]);
    app(OrderService::class)->markPaid($card, null, 'Kartla ödendi.', $payment);

    Notification::assertSentOnDemandTimes(NewOrderAdminNotification::class, 2);
});

it('closes returns when the return period is 0 days', function () {
    $order = ($this->place)();
    $order->forceFill(['status' => OrderStatus::Shipped, 'shipped_at' => now()])->save();

    ShopSettings::save(['return_days' => '14']);
    expect(app(ReturnService::class)->isEligible($order->fresh()))->toBeTrue();

    ShopSettings::save(['return_days' => '0']);
    expect(app(ReturnService::class)->isEligible($order->fresh()))->toBeFalse();
});

it('does not let a manual stock-out exceed what is on hand', function () {
    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'operation' => 'out', 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 11,
    ])->assertRedirect()->assertSessionHas('toast.type', 'warning');

    expect($this->product->fresh()->stock)->toBe(10);

    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'operation' => 'out', 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect($this->product->fresh()->stock)->toBe(6);
});

it('shows the bank accounts on a dealer order waiting for a bank transfer', function () {
    $order = ($this->place)();

    $this->actingAs($this->member)->get(route('app.shop.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->has('bankAccounts', 1)->where('bankAccounts.0.bank_name', 'Test Bankası'));

    app(OrderService::class)->markPaid($order, $this->admin);

    $this->actingAs($this->member)->get(route('app.shop.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('bankAccounts', null));
});

it('uses the app name from the general settings in email subjects', function () {
    Setting::query()->updateOrCreate(['key' => 'app_name'], ['value' => 'Yeni Firma', 'group' => 'general']);
    Setting::flushCache();

    $order = ($this->place)();

    expect((new OrderReceivedMail($order))->envelope()->subject)->toStartWith('Yeni Firma');
});

it('lets an admin reset another user’s two-factor authentication', function () {
    $this->member->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($this->admin)->get(route('panel.members.show', $this->member))
        ->assertInertia(fn (Assert $page) => $page->where('user.data.two_factor_enabled', true));

    $this->actingAs($this->admin)->delete(route('panel.members.two-factor.reset', $this->member))
        ->assertSessionHas('toast.type', 'success');

    expect($this->member->fresh()->two_factor_secret)->toBeNull()
        ->and($this->member->fresh()->two_factor_confirmed_at)->toBeNull();

    // Kendi doğrulamasını buradan sıfırlayamaz.
    $this->admin->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->admin)->delete(route('panel.settings.users.two-factor.reset', $this->admin))
        ->assertSessionHas('toast.type', 'warning');
    expect($this->admin->fresh()->two_factor_secret)->not->toBeNull();
});
