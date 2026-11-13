<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\StockMovementType;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Shop\OrderService;
use App\Services\Shop\StockService;
use App\Support\Api\ApiAbilities;
use App\Support\Webhooks\WebhookEvents;
use App\Support\Webhooks\WebhookService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** @param  list<string>  $abilities */
function platformToken(User $user, array $abilities): string
{
    app('auth')->forgetGuards();

    return $user->createToken('platform', $abilities)->plainTextToken;
}

function platformApi(string $token): mixed
{
    app('auth')->forgetGuards();

    return test()->withToken($token)->withHeader('Accept', 'application/json');
}

function platformProduct(string $sku, array $attributes = []): Product
{
    return Product::query()->create([
        'sku' => $sku, 'barcode' => "869{$sku}", 'name' => "Ürün {$sku}", 'slug' => strtolower($sku), 'price' => '100.00',
        'currency_id' => Currency::query()->where('code', 'TRY')->value('id'), 'tax_rate' => 20, 'sale_mode' => 'cart',
        'unit' => 'Adet', 'status' => Status::Active, 'tracking' => StockTracking::None, 'track_stock' => true,
        ...$attributes,
    ])->fresh();
}

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->admin = User::factory()->admin()->create(['name' => 'Entegrasyon']);
    $this->warehouse = Warehouse::query()->where('code', 'DEPO')->firstOrFail();
});

test('the api describes itself and serves its openapi document without a token', function () {
    $token = platformToken($this->admin, [ApiAbilities::PRODUCTS_READ]);

    platformApi($token)->getJson(route('api.v1.index'))->assertOk()
        ->assertJsonPath('data.api_version', 'v1')
        ->assertJsonPath('data.token.abilities', [ApiAbilities::PRODUCTS_READ])
        ->assertJsonPath('data.mcp', route('mcp'));

    $this->get(route('api.v1.openapi'))->assertOk()->assertHeader('content-type', 'application/yaml; charset=utf-8');
});

test('products are created, searched, repriced in bulk and deleted', function () {
    $category = Category::query()->create(['name' => 'Kablolar', 'slug' => 'kablolar', 'status' => Status::Active]);
    $token = platformToken($this->admin, [ApiAbilities::PRODUCTS_READ, ApiAbilities::PRODUCTS_WRITE]);

    platformApi($token)->postJson(route('api.v1.products.store'), [
        'sku' => 'API-1', 'name' => 'Örnek Kablo', 'price' => '125.50', 'tax_rate' => 20, 'category' => 'kablolar',
    ])->assertCreated()->assertJsonPath('data.sku', 'API-1')->assertJsonPath('data.prices.price', '125.5000');

    expect(Product::query()->where('sku', 'API-1')->value('category_id'))->toBe($category->id);
    platformApi($token)->postJson(route('api.v1.products.store'), ['sku' => 'API-1', 'name' => 'x', 'price' => 1, 'tax_rate' => 20])->assertUnprocessable();

    platformApi($token)->getJson(route('api.v1.products.index', ['q' => 'kablo']))->assertOk()->assertJsonCount(1, 'data');

    platformProduct('API-2');
    platformApi($token)->putJson(route('api.v1.prices.update'), ['items' => [
        ['sku' => 'API-1', 'price' => '125.50'],
        ['sku' => 'API-2', 'price' => '90'],
    ]])->assertOk()->assertJsonPath('meta.changed', 1)->assertJsonPath('data.1.price', '90.0000');
    expect((string) Product::query()->where('sku', 'API-2')->first()->price)->toBe('90.0000');

    platformApi($token)->putJson(route('api.v1.prices.update'), ['items' => [['sku' => 'YOK', 'price' => 1]]])->assertUnprocessable();

    platformApi($token)->deleteJson(route('api.v1.products.destroy', 'API-2'))->assertNoContent();
    expect(Product::query()->where('sku', 'API-2')->exists())->toBeFalse();
});

test('stock is listed per warehouse and reference data is readable', function () {
    $product = platformProduct('STK-1');
    app(StockService::class)->change($product, $this->warehouse, 7, StockMovementType::In);
    $token = platformToken($this->admin, [ApiAbilities::PRODUCTS_READ, ApiAbilities::ORDERS_READ, ApiAbilities::ACCOUNTS_READ]);

    platformApi($token)->getJson(route('api.v1.stock.index', ['sku' => 'STK-1']))->assertOk()
        ->assertJsonPath('data.0.warehouse', 'DEPO')->assertJsonPath('data.0.quantity', 7);

    platformApi($token)->getJson(route('api.v1.warehouses.index'))->assertOk()->assertJsonFragment(['code' => 'DEPO']);
    platformApi($token)->getJson(route('api.v1.currencies.index'))->assertOk()->assertJsonFragment(['code' => 'TRY']);
    platformApi($token)->getJson(route('api.v1.carriers.index'))->assertOk();
    platformApi($token)->getJson(route('api.v1.dealer-groups.index'))->assertOk();
});

test('categories and brands are written with catalog:write only', function () {
    $reader = platformToken($this->admin, [ApiAbilities::PRODUCTS_READ]);
    platformApi($reader)->postJson(route('api.v1.categories.store'), ['name' => 'Aydınlatma'])->assertForbidden();

    $token = platformToken($this->admin, [ApiAbilities::PRODUCTS_READ, ApiAbilities::CATALOG_WRITE]);
    platformApi($token)->postJson(route('api.v1.categories.store'), ['name' => 'Aydınlatma'])->assertCreated()->assertJsonPath('data.slug', 'aydinlatma');
    platformApi($token)->postJson(route('api.v1.categories.store'), ['name' => 'Lambalar', 'parent' => 'aydinlatma'])->assertCreated()->assertJsonPath('data.parent', 'aydinlatma');
    platformApi($token)->patchJson(route('api.v1.categories.update', 'lambalar'), ['status' => 'passive'])->assertOk()->assertJsonPath('data.status', 'passive');

    platformApi($token)->postJson(route('api.v1.brands.store'), ['name' => 'Örnek Marka'])->assertCreated()->assertJsonPath('data.slug', 'ornek-marka');
    platformApi($token)->patchJson(route('api.v1.brands.update', 'ornek-marka'), ['name' => 'Yeni Ad'])->assertOk()->assertJsonPath('data.name', 'Yeni Ad');
    expect(Brand::query()->count())->toBeGreaterThan(0);
});

test('accounts are opened, updated and their transactions listed', function () {
    $token = platformToken($this->admin, [ApiAbilities::ACCOUNTS_READ, ApiAbilities::ACCOUNTS_WRITE, ApiAbilities::LEDGER_WRITE]);

    platformApi($token)->postJson(route('api.v1.accounts.store'), ['code' => 'ERP001', 'title' => 'ERP Bayisi', 'tax_number' => '1234567890', 'discount_rate' => 5])
        ->assertCreated()->assertJsonPath('data.code', 'ERP001')->assertJsonPath('data.type', 'dealer');

    platformApi($token)->postJson(route('api.v1.accounts.store'), ['title' => 'Kodsuz'])->assertCreated();
    platformApi($token)->patchJson(route('api.v1.accounts.update', 'ERP001'), ['title' => 'ERP Bayisi A.Ş.'])->assertOk()->assertJsonPath('data.title', 'ERP Bayisi A.Ş.');
    platformApi($token)->patchJson(route('api.v1.accounts.update', 'ERP001'), ['code' => 'BASKA'])->assertUnprocessable();

    platformApi($token)->postJson(route('api.v1.accounts.transactions.store', 'ERP001'), ['type' => 'debit', 'amount' => '250', 'reference' => 'DK-1'])->assertCreated();
    platformApi($token)->getJson(route('api.v1.accounts.transactions.index', 'ERP001'))->assertOk()->assertJsonCount(1, 'data');
});

test('orders are opened over the api at the dealer price; a repeated idempotency key does not open a second order', function () {
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    $account = Account::query()->create(['code' => 'BAYI9', 'title' => 'Bayi Dokuz', 'type' => CustomerType::Dealer]);
    $account->forceFill(['discount_rate' => 10])->save();
    $product = platformProduct('ORD-1');
    app(StockService::class)->change($product, $this->warehouse, 20, StockMovementType::In);
    $token = platformToken($this->admin, [ApiAbilities::ORDERS_WRITE, ApiAbilities::ORDERS_READ]);

    $payload = [
        'account' => 'BAYI9', 'payment_method' => 'bank_transfer',
        'lines' => [['sku' => 'ORD-1', 'quantity' => 2]],
        'shipping' => ['name' => 'Depo', 'address' => 'Sanayi Cd. 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
    ];

    // 100 − %10 = 90 + %20 KDV = 108,00 × 2 = 216,00
    $first = platformApi($token)->withHeader('Idempotency-Key', 'siparis-1')->postJson(route('api.v1.orders.store'), $payload)
        ->assertCreated()->assertJsonPath('data.totals.grand_total', '216.00');

    platformApi($token)->withHeader('Idempotency-Key', 'siparis-1')->postJson(route('api.v1.orders.store'), $payload)
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.number', $first->json('data.number'));

    platformApi($token)->withHeader('Idempotency-Key', 'siparis-1')->postJson(route('api.v1.orders.store'), [...$payload, 'note' => 'farklı'])->assertUnprocessable();

    expect(Order::query()->where('account_id', $account->id)->count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(18);

    platformApi($token)->postJson(route('api.v1.orders.store'), [...$payload, 'lines' => [['sku' => 'YOK-1', 'quantity' => 1]]])->assertUnprocessable();
});

test('webhooks are registered over the api and receive signed order events', function () {
    Http::fake(['hooks.test/*' => Http::response('ok')]);
    $token = platformToken($this->admin, [ApiAbilities::WEBHOOKS_MANAGE]);

    $created = platformApi($token)->postJson(route('api.v1.webhooks.store'), ['url' => 'https://hooks.test/bayi', 'events' => [WebhookEvents::ORDER_STATUS_CHANGED]])
        ->assertCreated();
    $secret = $created->json('data.secret');
    expect($secret)->toStartWith('whsec_');

    platformApi($token)->getJson(route('api.v1.webhooks.index'))->assertOk()->assertJsonMissingPath('data.0.secret');

    $order = Order::query()->create([
        'number' => 'S-WH-1', 'status' => OrderStatus::Pending, 'payment_status' => 'pending', 'payment_method' => 'bank_transfer',
        'customer_name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '0555', 'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali'],
        'currency_code' => 'TRY', 'subtotal' => '100.00', 'tax_total' => '20.00', 'grand_total' => '120.00',
    ]);
    app(OrderService::class)->changeStatus($order, OrderStatus::Processing, [], $this->admin);

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->status)->toBe(WebhookDelivery::SUCCEEDED)
        ->and($delivery->event)->toBe(WebhookEvents::ORDER_STATUS_CHANGED);

    Http::assertSent(function (HttpRequest $request) use ($secret) {
        [$t, $v1] = sscanf($request->header('X-Bayi-Signature')[0], 't=%d,v1=%s');

        return $request->url() === 'https://hooks.test/bayi'
            && hash_equals(hash_hmac('sha256', $t.'.'.$request->body(), $secret), $v1)
            && $request['event'] === 'order.status_changed'
            && $request['data']['order']['number'] === 'S-WH-1'
            && $request['data']['previous_status'] === 'pending';
    });

    $id = $created->json('data.id');
    platformApi($token)->postJson(route('api.v1.webhooks.ping', $id))->assertStatus(202);
    platformApi($token)->getJson(route('api.v1.webhooks.deliveries', $id))->assertOk()->assertJsonCount(2, 'data');
    platformApi($token)->patchJson(route('api.v1.webhooks.update', $id), ['active' => false])->assertOk()->assertJsonPath('data.active', false);
    platformApi($token)->deleteJson(route('api.v1.webhooks.destroy', $id))->assertNoContent();
    expect(WebhookEndpoint::query()->count())->toBe(0);
});

test('a failing webhook is retried and recorded', function () {
    Http::fake(['hooks.test/*' => Http::response('hata', 500)]);
    $endpoint = WebhookEndpoint::query()->create(['url' => 'https://hooks.test/x', 'secret' => 'whsec_x', 'events' => ['*'], 'active' => true]);

    app(WebhookService::class)->queue($endpoint, WebhookEvents::PING, []);

    $delivery = WebhookDelivery::query()->firstOrFail();
    expect($delivery->attempts)->toBe(1)
        ->and($delivery->status)->toBe(WebhookDelivery::PENDING)
        ->and($delivery->response_status)->toBe(500)
        ->and($endpoint->fresh()->failure_count)->toBe(1);
});

test('the mcp server lists only the tools the token may use and runs them through the api', function () {
    platformProduct('MCP-1', ['name' => 'Mcp Kablosu']);
    $token = platformToken($this->admin, [ApiAbilities::PRODUCTS_READ]);
    $rpc = fn (array $message) => platformApi($token)->postJson(route('mcp'), ['jsonrpc' => '2.0', ...$message]);

    $rpc(['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1']]])
        ->assertOk()->assertJsonPath('result.protocolVersion', '2025-06-18')->assertJsonPath('result.serverInfo.name', 'bayiyazilimi');

    platformApi($token)->postJson(route('mcp'), ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);

    $tools = collect($rpc(['id' => 2, 'method' => 'tools/list'])->assertOk()->json('result.tools'))->pluck('name');
    expect($tools)->toContain('search_products', 'get_product', 'get_stock', 'get_api_info')
        ->not->toContain('create_order', 'update_prices', 'list_accounts');

    $rpc(['id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'search_products', 'arguments' => ['q' => 'mcp']]])
        ->assertOk()->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.data.0.sku', 'MCP-1');

    $rpc(['id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'get_product', 'arguments' => ['sku' => 'YOK']]])
        ->assertOk()->assertJsonPath('result.isError', true);

    $rpc(['id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'create_order', 'arguments' => []]])
        ->assertOk()->assertJsonPath('error.code', -32602);

    $rpc(['id' => 6, 'method' => 'resources/list'])->assertOk()->assertJsonPath('error.code', -32601);

    app('auth')->forgetGuards();
    $this->flushHeaders()->postJson(route('mcp'), ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping'])->assertUnauthorized();
});

test('the panel webhook screen adds, toggles and deletes webhooks', function () {
    $this->actingAs($this->admin);

    $this->post(route('panel.settings.webhooks.store'), ['url' => 'https://hooks.test/panel', 'events' => ['*']])
        ->assertRedirect(route('panel.settings.webhooks.index'));
    $endpoint = WebhookEndpoint::query()->firstOrFail();

    $this->get(route('panel.settings.webhooks.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/settings/webhooks/index')->has('webhooks', 1)->where('plainSecret', fn ($s) => str_starts_with((string) $s, 'whsec_')));

    $this->patch(route('panel.settings.webhooks.update', $endpoint), ['active' => false])->assertRedirect();
    expect($endpoint->fresh()->active)->toBeFalse();

    $this->delete(route('panel.settings.webhooks.destroy', $endpoint))->assertRedirect();
    expect(WebhookEndpoint::query()->count())->toBe(0);
});
