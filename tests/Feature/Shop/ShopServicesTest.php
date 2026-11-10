<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\QuoteStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\ExchangeRateService;
use App\Services\Shop\OrderService;
use App\Services\Shop\PriceCalculator;
use App\Services\Shop\QuoteService;
use App\Services\Shop\StockService;
use Database\Seeders\ShopSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);

    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);

    // Bu testler kargo dışı tutarları doğrular; desi kargosu ShippingAndExportTest'te.
    Carrier::query()->update(['status' => Status::Passive->value]);

    $this->eur = Currency::query()->where('code', 'EUR')->first();
    $this->eur->update(['exchange_rate' => 50, 'rate_source' => RateSource::Manual]);
    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->main = Warehouse::query()->where('code', 'DEPO')->first();
    $this->second = Warehouse::query()->create(['code' => 'D2', 'name' => 'İkinci Depo', 'is_sellable' => true, 'status' => Status::Active, 'sort_order' => 2]);

    $this->product = Product::create([
        'sku' => 'TEST-1',
        'name' => 'Test Süpürge',
        'slug' => 'test-supurge',
        'price' => 100,
        'currency_id' => $this->eur->id,
        'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart,
        'status' => Status::Active,
    ]);
});

function checkoutData(string $method = 'bank_transfer'): array
{
    return [
        'email' => 'musteri@example.com',
        'phone' => '05550000000',
        'billing' => ['type' => 'individual', 'name' => 'Ayşe Yılmaz', 'address' => 'Adres 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => true,
        'payment_method' => $method,
    ];
}

it('converts product prices between currencies and adds tax', function () {
    $prices = app(PriceCalculator::class);

    expect($prices->net($this->product->fresh('currency'), $this->try))->toEqualDecimal('5000.0')
        ->and($prices->gross($this->product->fresh('currency'), $this->try))->toEqualDecimal('6000.0')
        ->and($prices->gross($this->product->fresh('currency'), $this->eur))->toEqualDecimal('120.0');

    $usd = Currency::query()->where('code', 'USD')->first();
    $usd->update(['exchange_rate' => 40]);

    expect(app(CurrencyManager::class)->convert(100, $this->eur, $usd->fresh()))->toEqualDecimal('125.0');
});

it('tracks stock per warehouse with movements', function () {
    $stock = app(StockService::class);

    $stock->change($this->product, $this->main, 10, StockMovementType::In);
    $stock->change($this->product, $this->second, 3, StockMovementType::In);
    expect($this->product->fresh()->stock)->toBe(13);

    $stock->transfer($this->product, $this->main, $this->second, 4);
    expect($this->product->stocks()->where('warehouse_id', $this->second->id)->value('quantity'))->toBe(7)
        ->and($this->product->fresh()->stock)->toBe(13);

    $stock->setQuantity($this->product, $this->main, 2);
    expect($this->product->fresh()->stock)->toBe(9)
        ->and($this->product->movements()->count())->toBe(5);

    expect(fn () => $stock->transfer($this->product, $this->main, $this->second, 50))
        ->toThrow(ShopException::class);
});

it('excludes non-sellable warehouses from the product stock total', function () {
    $this->second->update(['is_sellable' => false]);
    $stock = app(StockService::class);

    $stock->change($this->product, $this->main, 5, StockMovementType::In);
    $stock->change($this->product, $this->second, 5, StockMovementType::In);

    expect($this->product->fresh()->stock)->toBe(5);
});

it('places a bank transfer order from the cart and reserves stock; cancel restores it', function () {
    $stock = app(StockService::class);
    $stock->change($this->product, $this->main, 2, StockMovementType::In);
    $stock->change($this->product, $this->second, 5, StockMovementType::In);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 4);

    $order = app(OrderService::class)->placeFromCart($cart, checkoutData(), null, $this->try);

    expect($order->grand_total)->toBeMoney('24000.00')
        ->and($order->tax_total)->toBeMoney('4000.00')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->stock_deducted)->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(3)
        ->and($cart->isEmpty())->toBeTrue();

    // Varsayılan depodan önce düşer, kalan diğer depodan.
    expect($this->product->stocks()->where('warehouse_id', $this->main->id)->value('quantity'))->toBe(0);

    $admin = User::factory()->create();
    app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled, ['note' => 'Müşteri vazgeçti'], $admin);

    expect($this->product->fresh()->stock)->toBe(7)
        ->and($order->fresh()->stock_deducted)->toBeFalse();
});

it('refuses to sell more than available stock or quote-only products', function () {
    app(StockService::class)->change($this->product, $this->main, 1, StockMovementType::In);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 3);

    expect(fn () => app(OrderService::class)->placeFromCart($cart, checkoutData(), null, $this->try))
        ->toThrow(ShopException::class);

    $this->product->update(['sale_mode' => SaleMode::Quote]);
    $cart->set($this->product, 1);

    expect(fn () => app(OrderService::class)->placeFromCart($cart, checkoutData(), null, $this->try))
        ->toThrow(ShopException::class);
});

it('releases reserved stock when payment fails and deducts it again once paid', function () {
    app(StockService::class)->change($this->product, $this->main, 5, StockMovementType::In);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 2);
    $orders = app(OrderService::class);
    $order = $orders->placeFromCart($cart, checkoutData(), null, $this->try);

    expect($this->product->fresh()->stock)->toBe(3);

    $orders->markPaymentFailed($order, 'Kart reddedildi');

    expect($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->fresh()->stock_deducted)->toBeFalse()
        ->and($this->product->fresh()->stock)->toBe(5);

    $orders->markPaid($order->fresh());

    expect($order->fresh()->stock_deducted)->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(3);
});

it('refuses to reopen a cancelled order when stock is no longer enough', function () {
    $stock = app(StockService::class);
    $stock->change($this->product, $this->main, 2, StockMovementType::In);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 2);
    $orders = app(OrderService::class);
    $order = $orders->placeFromCart($cart, checkoutData(), null, $this->try);
    $admin = User::factory()->create();

    $orders->changeStatus($order, OrderStatus::Cancelled, [], $admin);
    $stock->change($this->product, $this->main, -1, StockMovementType::Out);

    expect(fn () => $orders->changeStatus($order->fresh(), OrderStatus::Processing, [], $admin))
        ->toThrow(ShopException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->stock_deducted)->toBeFalse()
        ->and($this->product->fresh()->stock)->toBe(1);

    // Ön siparişe açık üründe eksiye düşerek açılır.
    $this->product->update(['allow_backorder' => true]);
    $orders->changeStatus($order->fresh(), OrderStatus::Processing, [], $admin);

    expect($order->fresh()->stock_deducted)->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(-1);
});

it('runs the quote workflow: request, price, send, customer approves, order', function () {
    app(StockService::class)->change($this->product, $this->main, 10, StockMovementType::In);
    $admin = User::factory()->create();
    $service = app(QuoteService::class);

    $list = Basket::quote();
    $list->add($this->product->fresh(), 3);

    $quote = $service->request($list, [
        'customer_name' => 'Firma Yetkilisi', 'company' => 'ACME', 'email' => 'acme@example.com',
    ], null, $this->try);

    expect($quote->status)->toBe(QuoteStatus::Pending)
        ->and($quote->items)->toHaveCount(1)
        ->and($quote->items->first()->list_price)->toEqualDecimal('5000.0');

    // Fiyat girilmeden gönderilemez; müşteri gönderilmemiş teklifi onaylayamaz.
    expect(fn () => $service->send($quote, $admin))->toThrow(ShopException::class)
        ->and(fn () => $service->approve($quote, null))->toThrow(ShopException::class);

    $service->save($quote->fresh(), [
        'items' => [['product_id' => $this->product->id, 'unit_price' => '4000', 'quantity' => 3, 'tax_rate' => 20]],
        'shipping_total' => '0',
    ], $admin);
    $service->send($quote->fresh(), $admin, 'Teslim 3 gün.');
    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Sent)
        ->and($quote->grand_total)->toBeMoney('14400.00')
        ->and($quote->items->first()->list_price)->toEqualDecimal('5000.0')
        ->and($quote->valid_until)->not->toBeNull()
        ->and($quote->canRespond())->toBeTrue()
        ->and($quote->canBeOrdered())->toBeFalse();

    // Revize → yönetici yeniden gönderir → müşteri onaylar.
    $service->requestRevision($quote, null, '5 adet olsun');
    expect($quote->fresh()->status)->toBe(QuoteStatus::Revision);
    $service->send($quote->fresh(), $admin);
    $service->approve($quote->fresh(), null);
    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Approved)
        ->and($quote->canBeOrdered())->toBeTrue()
        ->and($quote->histories()->count())->toBe(5);

    $order = app(OrderService::class)->placeFromQuote($quote, checkoutData(), null);

    expect($order->grand_total)->toBeMoney('14400.00')
        ->and($order->quote_id)->toBe($quote->id)
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Ordered)
        ->and($quote->fresh()->canBeOrdered())->toBeFalse()
        ->and($this->product->fresh()->stock)->toBe(7);

    // Ödenmemiş sipariş iptal edilince teklif yeniden ödenebilir olur.
    app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled, [], $admin);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Approved)
        ->and($quote->fresh()->order_id)->toBeNull();
});

it('supports rejecting, withdrawing and cancelling quotes', function () {
    $admin = User::factory()->create();
    $service = app(QuoteService::class);
    $list = Basket::quote();
    $list->add($this->product->fresh(), 1);

    $quote = $service->request($list, ['customer_name' => 'A', 'email' => 'a@example.com'], null, $this->try);
    $service->save($quote->fresh(), ['items' => [['product_id' => $this->product->id, 'unit_price' => '10', 'quantity' => 1, 'tax_rate' => 20]]], $admin);
    $service->send($quote->fresh(), $admin);
    $service->reject($quote->fresh(), null, 'Pahalı');
    expect($quote->fresh()->status)->toBe(QuoteStatus::Rejected)
        ->and(fn () => $service->cancel($quote->fresh(), $admin))->toThrow(ShopException::class);

    $list->add($this->product->fresh(), 1);
    $quote2 = $service->request($list, ['customer_name' => 'B', 'email' => 'b@example.com'], null, $this->try);
    $service->withdraw($quote2, null);
    expect($quote2->fresh()->status)->toBe(QuoteStatus::Cancelled);

    $list->add($this->product->fresh(), 1);
    $quote3 = $service->request($list, ['customer_name' => 'C', 'email' => 'c@example.com'], null, $this->try);
    $service->cancel($quote3, $admin, 'Tedarik yok');
    expect($quote3->fresh()->status)->toBe(QuoteStatus::Cancelled);
});

it('parses TCMB rates xml', function () {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Tarih_Date Tarih="31.10.2026">
  <Currency CurrencyCode="USD"><Unit>1</Unit><ForexBuying>41.9</ForexBuying><ForexSelling>42.1</ForexSelling></Currency>
  <Currency CurrencyCode="EUR"><Unit>1</Unit><ForexBuying>48.4</ForexBuying><ForexSelling>48.6</ForexSelling></Currency>
  <Currency CurrencyCode="JPY"><Unit>100</Unit><ForexBuying>27.0</ForexBuying><ForexSelling>27.5</ForexSelling></Currency>
</Tarih_Date>
XML;

    $rates = app(ExchangeRateService::class)->parseTcmbXml($xml);

    expect($rates['USD'])->toEqualDecimal('42.1')
        ->and($rates['EUR'])->toEqualDecimal('48.6')
        ->and($rates['JPY'])->toEqualDecimal('0.275');
});

it('updates only tcmb-sourced currencies when syncing', function () {
    Http::fake(['*' => Http::response('<Tarih_Date><Currency CurrencyCode="USD"><Unit>1</Unit><ForexSelling>43.5</ForexSelling></Currency><Currency CurrencyCode="EUR"><Unit>1</Unit><ForexSelling>51</ForexSelling></Currency></Tarih_Date>')]);

    $updated = app(ExchangeRateService::class)->syncFromTcmb();

    expect($updated)->toBeMoney(['USD' => '43.500000'])
        ->and(Currency::query()->where('code', 'USD')->value('exchange_rate'))->toEqualDecimal('43.5')
        ->and(Currency::query()->where('code', 'EUR')->value('exchange_rate'))->toEqualDecimal('50.0');
});

it('sends a bank transfer order with the payment method enum', function () {
    app(StockService::class)->change($this->product, $this->main, 1, StockMovementType::In);
    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 1);

    $order = app(OrderService::class)->placeFromCart($cart, checkoutData(), null, $this->eur);

    expect($order->payment_method)->toBe('bank_transfer')
        ->and($order->currency_code)->toBe('EUR')
        ->and($order->grand_total)->toBeMoney('120.00');
});
