<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\QuoteStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function panelShopCurrency(string $code = 'TRY', float $rate = 1): Currency
{
    return Currency::query()->updateOrCreate(['code' => $code], [
        'name' => $code,
        'symbol' => $code === 'TRY' ? '₺' : '€',
        'decimal_places' => 2,
        'thousands_separator' => '.',
        'decimal_separator' => ',',
        'exchange_rate' => $rate,
        'rate_source' => RateSource::Manual,
        'status' => Status::Active,
        'sort_order' => 1,
    ]);
}

function panelShopWarehouse(string $code = 'MRK', bool $default = true): Warehouse
{
    return Warehouse::query()->updateOrCreate(['code' => $code], [
        'name' => "Depo {$code}",
        'is_default' => $default,
        'is_sellable' => true,
        'status' => Status::Active,
    ]);
}

function panelShopProduct(Currency $currency, array $attributes = []): Product
{
    return Product::query()->create(array_merge([
        'sku' => 'SKU-'.fake()->unique()->numerify('####'),
        'name' => 'Test Ürün',
        'slug' => 'test-urun-'.fake()->unique()->numerify('####'),
        'price' => 100,
        'currency_id' => $currency->id,
        'tax_rate' => 20,
        'sale_mode' => 'cart',
        'status' => Status::Active,
    ], $attributes));
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

test('panel shop index screens render', function (string $route, string $component) {
    panelShopCurrency();
    panelShopWarehouse();

    $this->actingAs($this->admin)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['panel.shop.products.index', 'panel/shop/products/index'],
    ['panel.shop.products.create', 'panel/shop/products/create'],
    ['panel.shop.categories.index', 'panel/shop/categories/index'],
    ['panel.shop.brands.index', 'panel/shop/brands/index'],
    ['panel.shop.warehouses.index', 'panel/shop/warehouses/index'],
    ['panel.shop.stock.index', 'panel/shop/stock/index'],
    ['panel.shop.stock.movements', 'panel/shop/stock/movements'],
    ['panel.shop.orders.index', 'panel/shop/orders/index'],
    ['panel.shop.quotes.index', 'panel/shop/quotes/index'],
    ['panel.shop.exchange-rates.index', 'panel/tools/definitions/exchange-rates/index'],
    ['panel.shop.carriers.index', 'panel/tools/definitions/carriers/index'],
    ['panel.shop.contracts.index', 'panel/tools/definitions/contracts/index'],
    ['panel.shop.contracts.create', 'panel/tools/definitions/contracts/edit'],
    ['panel.shop.stock-settings.edit', 'panel/shop/stock/settings'],
    ['panel.shop.sales-settings.edit', 'panel/tools/definitions/sales/index'],
    ['panel.shop.import.index', 'panel/shop/import/index'],
]);

test('member cannot access panel shop screens', function () {
    $member = User::factory()->member()->create();

    $this->actingAs($member)
        ->get(route('panel.shop.products.index'))
        ->assertRedirect();
});

test('admin can create and update a product', function () {
    $currency = panelShopCurrency();
    $brand = Brand::query()->create(['name' => 'Karcher', 'slug' => 'karcher']);
    $category = Category::query()->create(['name' => 'Süpürgeler', 'slug' => 'supurgeler']);

    $response = $this->actingAs($this->admin)->post(route('panel.shop.products.store'), [
        'name' => 'Karcher NT 22/1',
        'sku' => '1.378-600.0',
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'price' => '1234,50',
        'currency_id' => $currency->id,
        'tax_rate' => '20',
        'sale_mode' => 'cart',
        'track_stock' => '1',
        'min_qty' => 1,
        'delivery_days' => 2,
        'unit' => 'Adet',
        'status' => 'active',
        'highlights' => "Güçlü motor\n\nUzun kablo",
    ]);

    $product = Product::query()->where('sku', '1.378-600.0')->firstOrFail();
    $response->assertRedirect(route('panel.shop.products.edit', $product->id));

    expect($product->slug)->toBe('karcher-nt-221')
        ->and($product->price)->toEqualDecimal('1234.5')
        ->and($product->highlights)->toBe(['Güçlü motor', 'Uzun kablo'])
        ->and($product->stock)->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.products.edit', $product->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/shop/products/edit')
            ->where('product.sku', '1.378-600.0'));

    $this->actingAs($this->admin)->put(route('panel.shop.products.update', $product->id), [
        'name' => 'Karcher NT 22/1 Ap',
        'sku' => '1.378-600.0',
        'price' => '999',
        'currency_id' => $currency->id,
        'tax_rate' => '10',
        'sale_mode' => 'quote',
        'min_qty' => 1,
        'delivery_days' => 0,
        'unit' => 'Adet',
        'status' => 'passive',
    ])->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->name)->toBe('Karcher NT 22/1 Ap')
        ->and($product->sale_mode->value)->toBe('quote')
        ->and($product->status)->toBe(Status::Passive);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'product', 'event' => 'created']);
});

test('category with children cannot be deleted', function () {
    $parent = Category::query()->create(['name' => 'Makineler', 'slug' => 'makineler']);
    Category::query()->create(['name' => 'Süpürge', 'slug' => 'supurge', 'parent_id' => $parent->id]);

    $this->actingAs($this->admin)
        ->delete(route('panel.shop.categories.destroy', $parent->id))
        ->assertRedirect();

    expect(Category::query()->whereKey($parent->id)->exists())->toBeTrue();
});

test('stock operations adjust warehouse quantities and product total', function () {
    $currency = panelShopCurrency();
    $main = panelShopWarehouse('MRK');
    $second = panelShopWarehouse('D2', false);
    $product = panelShopProduct($currency);

    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id,
        'operation' => 'in',
        'warehouse_id' => $main->id,
        'quantity' => 10,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->post(route('panel.shop.stock.transfer'), [
        'product_id' => $product->id,
        'from_warehouse_id' => $main->id,
        'to_warehouse_id' => $second->id,
        'quantity' => 4,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id,
        'operation' => 'count',
        'warehouse_id' => $second->id,
        'quantity' => 3,
    ])->assertSessionHasNoErrors();

    expect(ProductStock::query()->where('warehouse_id', $main->id)->value('quantity'))->toBe(6)
        ->and(ProductStock::query()->where('warehouse_id', $second->id)->value('quantity'))->toBe(3)
        ->and($product->fresh()->stock)->toBe(9);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.stock.movements', ['product' => $product->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('movements.data', 4));
});

test('warehouse detail lists its products and movements and exports only its stock', function () {
    $currency = panelShopCurrency();
    $main = panelShopWarehouse('MRK');
    $other = panelShopWarehouse('D2', false);
    $product = panelShopProduct($currency);

    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id, 'operation' => 'in', 'warehouse_id' => $main->id, 'quantity' => 7,
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id, 'operation' => 'in', 'warehouse_id' => $other->id, 'quantity' => 2,
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->get(route('panel.shop.warehouses.show', $main->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/shop/warehouses/show')
            ->has('stocks.data', 1)
            ->where('stocks.data.0.quantity', 7)
            ->has('movements.data', 1)
            ->where('totals.quantity', 7));

    $this->actingAs($this->admin)
        ->get(route('panel.shop.warehouses.show', [$main->id, 'q' => 'yok-boyle-urun']))
        ->assertInertia(fn (Assert $page) => $page->has('stocks.data', 0));

    $response = $this->actingAs($this->admin)->get(route('panel.shop.warehouses.export', $main->id));
    $response->assertOk()->assertDownload('depo-mrk-'.now()->format('Y-m-d').'.xlsx');
});

test('warehouse with stock cannot be deleted', function () {
    $currency = panelShopCurrency();
    panelShopWarehouse('MRK');
    $other = panelShopWarehouse('D2', false);
    ProductStock::query()->create(['product_id' => panelShopProduct($currency)->id, 'warehouse_id' => $other->id, 'quantity' => 2]);

    $this->actingAs($this->admin)->delete(route('panel.shop.warehouses.destroy', $other->id));

    expect(Warehouse::query()->whereKey($other->id)->exists())->toBeTrue();
});

test('warehouse with movement history cannot be deleted, an unused one can', function () {
    $currency = panelShopCurrency();
    panelShopWarehouse('MRK');
    $used = panelShopWarehouse('D2', false);
    $unused = panelShopWarehouse('D3', false);
    $product = panelShopProduct($currency);

    $stock = app(StockService::class);
    $stock->change($product, $used, 3, StockMovementType::In);
    $stock->change($product, $used, -3, StockMovementType::Out);

    $this->actingAs($this->admin)
        ->delete(route('panel.shop.warehouses.destroy', $used->id))
        ->assertSessionHas('toast.type', 'warning');

    expect(Warehouse::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(StockMovement::query()->where('warehouse_id', $used->id)->count())->toBe(2);

    $this->actingAs($this->admin)->delete(route('panel.shop.warehouses.destroy', $unused->id));

    expect(Warehouse::query()->whereKey($unused->id)->exists())->toBeFalse();
});

test('admin can price a quote request and send it to the customer', function () {
    $currency = panelShopCurrency();
    $product = panelShopProduct($currency);

    $quote = Quote::query()->create([
        'number' => 'T2610310001',
        'status' => QuoteStatus::Pending,
        'customer_name' => 'Ali Veli',
        'email' => 'ali@example.com',
        'currency_code' => 'TRY',
    ]);
    $quote->items()->create([
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'quantity' => 2,
        'list_price' => 100,
        'tax_rate' => 20,
    ]);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.quotes.edit', $quote->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/quotes/form')->has('quote.items', 1));

    // Fiyat girilmeden gönderilemez; yalnız kaydedilir.
    $this->actingAs($this->admin)->put(route('panel.shop.quotes.update', $quote->id), [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '', 'tax_rate' => '20']],
        'shipping_total' => '0',
        'send' => '1',
    ])->assertSessionHasErrors('items.0.unit_price');
    expect($quote->fresh()->status)->toBe(QuoteStatus::Pending);

    $this->actingAs($this->admin)->put(route('panel.shop.quotes.update', $quote->id), [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '90', 'tax_rate' => '20']],
        'shipping_total' => '0',
    ])->assertSessionHasNoErrors();

    expect($quote->fresh()->grand_total)->toEqualDecimal('216.0')
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Pending);

    $this->actingAs($this->admin)->put(route('panel.shop.quotes.update', $quote->id), [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '90', 'tax_rate' => '20']],
        'shipping_total' => '0',
        'send' => '1',
    ])->assertSessionHasNoErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent)
        ->and($quote->fresh()->valid_until)->not->toBeNull();
});

test('admin can view an order and confirm a bank transfer payment', function () {
    panelShopCurrency();
    $order = Order::query()->create([
        'number' => 'S2610310001',
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer',
        'customer_name' => 'Ayşe',
        'email' => 'ayse@example.com',
        'phone' => '5550000000',
        'billing_address' => ['name' => 'Ayşe', 'address' => 'Adres', 'city' => 'Bursa'],
        'shipping_address' => ['name' => 'Ayşe', 'address' => 'Adres', 'city' => 'Bursa'],
        'currency_code' => 'TRY',
        'grand_total' => 120,
    ]);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.orders.show', $order->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/orders/show')->where('order.number', 'S2610310001'));

    $this->actingAs($this->admin)
        ->patch(route('panel.shop.orders.payment', $order->id), ['payment_status' => 'paid'])
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::Processing);

    $this->actingAs($this->admin)
        ->patch(route('panel.shop.orders.status', $order->id), [
            'status' => 'shipped',
            'cargo_company' => 'Yurtiçi',
            'tracking_number' => '123',
            'notify' => '1',
        ])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($order->fresh()->tracking_number)->toBe('123');
});

test('exchange rates can be saved manually and synced from tcmb', function () {
    $try = panelShopCurrency('TRY');
    $eur = panelShopCurrency('EUR', 30);
    Setting::query()->updateOrCreate(['key' => 'default_currency_id'], ['value' => $try->id, 'group' => 'defaults']);

    $this->actingAs($this->admin)->put(route('panel.shop.exchange-rates.update'), [
        'rates' => [
            $try->id => ['exchange_rate' => '1', 'rate_source' => 'manual'],
            $eur->id => ['exchange_rate' => '45,5', 'rate_source' => 'tcmb'],
        ],
    ])->assertSessionHasErrors();

    $this->actingAs($this->admin)->put(route('panel.shop.exchange-rates.update'), [
        'rates' => [
            $try->id => ['exchange_rate' => '1', 'rate_source' => 'manual'],
            $eur->id => ['exchange_rate' => '45.5', 'rate_source' => 'tcmb'],
        ],
    ])->assertSessionHasNoErrors();

    expect($eur->fresh()->exchange_rate)->toEqualDecimal('45.5')
        ->and($eur->fresh()->rate_source)->toBe(RateSource::Tcmb);

    Http::fake([
        '*' => Http::response('<?xml version="1.0" encoding="UTF-8"?><Tarih_Date><Currency CurrencyCode="EUR"><Unit>1</Unit><ForexBuying>48.1</ForexBuying><ForexSelling>48.2</ForexSelling></Currency></Tarih_Date>'),
    ]);

    $this->actingAs($this->admin)->post(route('panel.shop.exchange-rates.sync'))->assertSessionHasNoErrors();

    expect($eur->fresh()->exchange_rate)->toEqualDecimal('48.2');
});

test('stock, sales and header/footer settings are saved from their own screens', function () {
    $main = Warehouse::query()->create(['code' => 'ANA2', 'name' => 'Ana 2', 'is_sellable' => true, 'status' => Status::Active]);
    // "Stokta olmayanları vitrinde göster" yalnız vitrin kuruluyken kaydedilir.
    hooks()->filter('shop.storefront_enabled', fn () => true, PHP_INT_MAX);

    $this->actingAs($this->admin)->put(route('panel.shop.stock-settings.update'), [
        'default_warehouse_id' => $main->id, 'show_out_of_stock' => '0', 'low_stock_threshold' => '8',
    ])->assertSessionHasNoErrors();

    expect(Warehouse::default()->id)->toBe($main->id)
        ->and(Warehouse::query()->where('is_default', true)->count())->toBe(1)
        ->and(ShopSettings::bool('show_out_of_stock'))->toBeFalse()
        ->and(ShopSettings::int('low_stock_threshold'))->toBe(8)
        ->and(ShopSettings::bool('low_stock_notify'))->toBeFalse();

    // Depo formu varsayılanı değiştiremez; varsayılan depo pasif yapılamaz.
    $this->actingAs($this->admin)->put(route('panel.shop.warehouses.update', $main->id), [
        'code' => 'ANA2', 'name' => 'Ana 2', 'is_sellable' => '1', 'status' => 'passive',
    ]);
    expect($main->fresh()->status)->toBe(Status::Active);

    hooks()->filter('shop.storefront_enabled', fn () => false, PHP_INT_MAX);
    $this->actingAs($this->admin)->put(route('panel.shop.sales-settings.update'), [
        'price_display' => 'net', 'dealer_price_display' => 'gross', 'quote_valid_days' => '10', 'return_days' => '0',
        'order_notification_email' => 'satis@ornek.test',
    ])->assertSessionHasNoErrors();

    // Ziyaretçi / müşteri fiyat gösterimi vitrinindir: vitrin yokken kayıtlı değere dokunulmaz.
    expect(ShopSettings::get('price_display'))->toBe('gross')
        ->and(ShopSettings::get('dealer_price_display'))->toBe('gross')
        ->and(ShopSettings::get('order_notification_email'))->toBe('satis@ornek.test')
        ->and(ShopSettings::int('quote_valid_days'))->toBe(10)
        ->and(ShopSettings::int('return_days'))->toBe(0);

    hooks()->filter('shop.storefront_enabled', fn () => true, PHP_INT_MAX);
    $this->actingAs($this->admin)->put(route('panel.shop.sales-settings.update'), [
        'price_display' => 'net', 'dealer_price_display' => 'gross', 'quote_valid_days' => '10', 'return_days' => '0',
    ])->assertSessionHasNoErrors();
    expect(ShopSettings::get('price_display'))->toBe('net');
});

test('corporate info is saved from its own page under general settings', function () {
    $this->actingAs($this->admin)->get(route('panel.shop.corporate.edit'))->assertOk()
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('panel/settings/general/corporate')->has('settings.mersis_number'));

    $country = Country::factory()->create(['code' => 'ZZ']);
    Setting::query()->updateOrCreate(['key' => 'default_country_id'], ['value' => $country->id, 'group' => 'defaults']);
    $city = City::factory()->create(['country_id' => $country->id, 'name' => 'Bursa']);
    $district = District::factory()->create(['city_id' => $city->id, 'name' => 'Nilüfer']);
    $otherCity = City::factory()->create(['country_id' => $country->id, 'name' => 'İzmir']);

    $this->actingAs($this->admin)
        ->put(route('panel.shop.corporate.update'), [
            'store_name' => 'Örnek A.Ş.', 'mersis_number' => '0123456789012345',
            'store_email' => 'info@ornek.test', 'store_phone' => '0224 000 00 00',
            'store_country_id' => $country->id, 'store_city_id' => $city->id, 'store_district_id' => $district->id,
            'store_address' => 'Demirci Mah. No:40', 'store_postcode' => '16110',
        ])
        ->assertSessionHasNoErrors();

    expect(ShopSettings::get('store_name'))->toBe('Örnek A.Ş.')
        ->and(ShopSettings::get('mersis_number'))->toBe('0123456789012345')
        ->and(ShopSettings::get('store_email'))->toBe('info@ornek.test')
        ->and(ShopSettings::bool('bank_transfer_enabled'))->toBeTrue()
        ->and(ShopSettings::storeAddressLine())->toBe('Demirci Mah. No:40, 16110 Nilüfer / Bursa');

    // İlçe seçilen ile ait olmalı.
    $this->actingAs($this->admin)->put(route('panel.shop.corporate.update'), [
        'store_name' => 'Örnek Mağaza', 'store_country_id' => $country->id, 'store_city_id' => $otherCity->id,
        'store_district_id' => $district->id, 'store_address' => 'x',
    ])->assertSessionHasErrors('store_district_id');

    $this->actingAs($this->admin)->put(route('panel.shop.corporate.update'), ['store_name' => ''])
        ->assertSessionHasErrors(['store_name', 'store_city_id', 'store_address']);

    $this->getJson(route('locations.cities', $country->id))->assertOk()->assertJsonCount(2);
});

test('the ETBİS QR code can only be managed while the storefront is installed', function () {
    hooks()->filter('shop.storefront_enabled', fn () => false, PHP_INT_MAX);
    $this->actingAs($this->admin)->delete(route('panel.shop.corporate.etbis.destroy'))->assertNotFound();
});

test('shipping settings and TCMB settings are saved from their definitions screens', function () {
    $this->actingAs($this->admin)->get(route('panel.shop.shipping-settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('panel/tools/definitions/shipping/index')->has('settings.free_shipping_threshold'));

    $this->actingAs($this->admin)->put(route('panel.shop.shipping-settings.update'), [
        'shipping_fee' => '75,50', 'free_shipping_threshold' => '2500', 'default_product_desi' => '2',
    ])->assertSessionHasNoErrors();

    expect(ShopSettings::get('shipping_fee'))->toBe('75.50')
        ->and(ShopSettings::get('free_shipping_threshold'))->toBe('2500');

    $this->actingAs($this->admin)->put(route('panel.shop.exchange-rates.settings'), [
        'tcmb_rate_type' => 'ForexBuying', 'tcmb_auto_update' => '0',
    ])->assertSessionHasNoErrors();

    expect(ShopSettings::get('tcmb_rate_type'))->toBe('ForexBuying')
        ->and(ShopSettings::bool('tcmb_auto_update'))->toBeFalse();

    $this->actingAs($this->admin)->put(route('panel.shop.exchange-rates.settings'), ['tcmb_rate_type' => 'Nope'])
        ->assertSessionHasErrors('tcmb_rate_type');
});
