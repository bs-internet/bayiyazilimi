<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\QuoteStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Shop\OrderStatusNotification;
use App\Notifications\Shop\QuoteStatusNotification;
use App\Services\Shop\OrderService;
use App\Services\Shop\QuoteService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    ShopSettings::save(['order_notification_email' => 'satis@ornek.test']);
    $this->withoutVite();
    Notification::fake();

    $this->admin = User::factory()->admin()->create();
    $this->dealer = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->customer = sampleCustomer();
    $try = Currency::query()->where('code', 'TRY')->firstOrFail();
    $warehouse = Warehouse::query()->where('code', 'DEPO')->firstOrFail();
    Warehouse::query()->update(['is_default' => false]);
    $warehouse->update(['is_default' => true]);

    $this->product = Product::create([
        'sku' => 'TK-100', 'name' => 'Teklif Ürünü', 'slug' => 'teklif-urunu',
        'price' => 100, 'currency_id' => $try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, $warehouse, 50, StockMovementType::In);
});

function adminQuotePayload(User $user, Product $product, array $extra = []): array
{
    return array_merge([
        'user_id' => $user->id,
        'currency_code' => 'TRY',
        'admin_note' => 'Size özel fiyat.',
        'shipping_total' => '120',
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => '80,50', 'tax_rate' => '20', 'unit' => 'Adet']],
    ], $extra);
}

function quoteCheckoutPayload(): array
{
    return [
        'email' => 'user@user.com', 'phone' => '05551112233',
        'same_address' => '1', 'billing_same_as_shipping' => '1',
        'billing' => ['type' => 'corporate', 'name' => 'Ali Veli', 'company' => 'ACME', 'tax_office' => 'Nilüfer', 'tax_number' => '1234567890', 'address' => 'Depo adresi 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'payment_method' => 'bank_transfer', 'agreement' => '1',
    ];
}

it('lets the admin draft a quote for a customer and send it', function () {
    $this->actingAs($this->admin)->getJson(route('panel.shop.quotes.products', ['q' => 'Teklif']))
        ->assertOk()->assertJsonPath('data.0.id', $this->product->id)->assertJsonPath('data.0.price', '100.00');

    // Taslak: müşteriye gitmez, müşteri göremez.
    $this->actingAs($this->admin)->post(route('panel.shop.quotes.store'), adminQuotePayload($this->dealer, $this->product))
        ->assertSessionHasNoErrors();

    $quote = Quote::query()->withoutGlobalScopes()->with('items')->firstOrFail();

    // 3 × 80,50 = 241,50 + %20 KDV 48,30 + kargo 120
    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->source)->toBe(Quote::SOURCE_ADMIN)
        ->and($quote->account_id)->toBe($this->dealer->account_id)
        ->and($quote->email)->toBe('user@user.com')
        ->and($quote->grand_total)->toEqualDecimal('409.8');

    Notification::assertNothingSent();

    $this->flushSession();
    $this->actingAs($this->dealer)->get(route('app.shop.quotes.show', $quote->id))->assertNotFound();

    $this->flushSession();
    $this->actingAs($this->admin)->get(route('panel.shop.quotes.edit', $quote->id))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('panel/shop/quotes/form')
            ->where('quote.items.0.unit_price', '80.5000')
            ->where('quote.can_delete', true));

    // Gönder: müşteriye e-posta.
    $this->actingAs($this->admin)->put(route('panel.shop.quotes.update', $quote->id), adminQuotePayload($this->dealer, $this->product, ['send' => '1']))
        ->assertSessionHasNoErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->fresh()->valid_until)->not->toBeNull();

    Notification::assertSentOnDemand(QuoteStatusNotification::class, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'user@user.com' && $n->quote->status === QuoteStatus::Sent);

    $this->actingAs($this->admin)->get(route('panel.shop.quotes.index', ['source' => 'admin']))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('panel/shop/quotes/index')->has('quotes.data', 1));
});

it('requires prices before sending and validates the form', function () {
    $this->actingAs($this->admin)->post(route('panel.shop.quotes.store'), adminQuotePayload($this->dealer, $this->product, [
        'send' => '1',
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '', 'tax_rate' => '20']],
    ]))->assertSessionHasErrors('items.0.unit_price');

    // Yönetici (üye olmayan) müşteri seçilemez; boş teklif kabul edilmez.
    $this->actingAs($this->admin)->post(route('panel.shop.quotes.store'), adminQuotePayload($this->admin, $this->product, ['items' => []]))
        ->assertSessionHasErrors(['user_id', 'items']);
});

it('respects the validity window', function () {
    $service = app(QuoteService::class);
    $quote = $service->save(null, adminQuotePayload($this->dealer, $this->product, [
        'valid_from' => now()->addDays(2)->toDateString(),
        'valid_until' => now()->addDays(5)->toDateString(),
    ]), $this->admin);
    $service->send($quote, $this->admin);

    expect($quote->fresh()->canRespond())->toBeFalse();
    expect(fn () => $service->approve($quote->fresh(), $this->dealer))->toThrow(ShopException::class);

    $this->travel(3)->days();
    expect($quote->fresh()->canRespond())->toBeTrue();

    $this->travel(5)->days();
    expect($quote->fresh()->canRespond())->toBeFalse()
        ->and($quote->fresh()->customerStatusLabel())->toBe('Süresi doldu');
});

it('turns a quote into an order only once even when a stale copy is submitted again', function () {
    $quote = tap(app(QuoteService::class)->save(null, adminQuotePayload($this->dealer, $this->product), $this->admin), function (Quote $q) {
        app(QuoteService::class)->send($q, $this->admin);
        app(QuoteService::class)->approve($q->fresh(), $this->dealer);
    })->fresh();
    $stale = Quote::query()->withoutGlobalScopes()->findOrFail($quote->id); // ikinci sekmenin gördüğü hali

    app(OrderService::class)->placeFromQuote($quote, quoteCheckoutPayload(), $this->dealer);

    expect(fn () => app(OrderService::class)->placeFromQuote($stale, quoteCheckoutPayload(), $this->dealer))
        ->toThrow(ShopException::class);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Ordered)
        ->and(Order::query()->where('quote_id', $quote->id)->count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(47);
});

it('mails the customer on every order status change, adding the note only when asked', function () {
    $order = app(OrderService::class)->placeFromQuote(
        tap(app(QuoteService::class)->save(null, adminQuotePayload($this->dealer, $this->product), $this->admin), function (Quote $q) {
            app(QuoteService::class)->send($q, $this->admin);
            app(QuoteService::class)->approve($q->fresh(), $this->dealer);
        })->fresh(),
        quoteCheckoutPayload(),
        $this->dealer,
    );

    app(OrderService::class)->changeStatus($order, OrderStatus::Processing, ['note' => 'İç not', 'notify' => false], $this->admin);
    Notification::assertSentOnDemand(OrderStatusNotification::class, fn ($n) => $n->order->is($order) && $n->note === null);

    app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Shipped, ['note' => 'Yarın teslim', 'notify' => true], $this->admin);
    Notification::assertSentOnDemand(OrderStatusNotification::class, fn ($n) => $n->note === 'Yarın teslim');
});
