<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\StockMovementType;
use App\Enums\Shop\StockTracking;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\CollectionService;
use App\Services\Shop\LedgerService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Api\ApiAbilities;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

function apiProduct(string $sku, array $attributes = []): Product
{
    return Product::query()->create([
        'sku' => $sku, 'barcode' => "869{$sku}", 'name' => "Ürün {$sku}", 'slug' => strtolower($sku), 'price' => '100.50',
        'compare_price' => '120', 'currency_id' => Currency::query()->value('id'), 'tax_rate' => 20, 'sale_mode' => 'cart',
        'unit' => 'Adet', 'status' => Status::Active, 'tracking' => StockTracking::None, 'track_stock' => true,
        ...$attributes,
    ])->fresh();
}

function apiOrder(string $number, OrderStatus $status, ?Account $account = null): Order
{
    $order = Order::query()->create([
        'number' => $number, 'status' => $status, 'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali Veli', 'email' => 'ali@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali Veli', 'address' => 'Merkez'], 'shipping_address' => ['name' => 'Ali Veli'],
        'currency_code' => 'TRY', 'subtotal' => '100.00', 'tax_total' => '20.00', 'grand_total' => '120.00',
        'account_id' => $account?->id,
    ]);

    $order->items()->create([
        'sku' => 'LINE-1', 'name' => 'Satır ürünü', 'quantity' => 2, 'unit_price' => '50.0000',
        'tax_rate' => 20, 'tax_amount' => '20.00', 'line_total' => '120.00',
    ]);

    return $order;
}

/**
 * Yeni anahtarla istek. Sanctum korumasının kullanıcısı uygulama ömrü boyunca
 * önbelleklenir; testte her anahtar değişiminde sıfırlanır.
 *
 * @param  list<string>  $abilities
 */
function apiAs(User $user, array $abilities): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

function withApiToken(string $token): mixed
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create(['name' => 'Entegrasyon']);
    $this->warehouse = Warehouse::query()->create(['code' => 'MERKEZ', 'name' => 'Merkez Depo', 'is_default' => false, 'is_sellable' => true, 'status' => Status::Active]);
    $this->other = Warehouse::query()->create(['code' => 'IZMIR', 'name' => 'İzmir Depo', 'is_default' => false, 'is_sellable' => true, 'status' => Status::Active]);
});

// ------------------------------------------------------------------ Kimlik / yetki

test('requests without a valid token get a json 401 in turkish', function () {
    $this->getJson(route('api.v1.products.index'))
        ->assertUnauthorized()
        ->assertJsonPath('message', __('Kimlik doğrulanamadı: geçerli bir API anahtarı gönderin (Authorization: Bearer …).'));

    // Accept başlığı olmadan da JSON döner (yönlendirme yok).
    $this->get(route('api.v1.products.index'))->assertUnauthorized()->assertHeader('content-type', 'application/json');

    withApiToken('1|yanlis')->getJson(route('api.v1.products.index'))->assertUnauthorized();
});

test('a token without the required ability gets 403', function () {
    $token = apiAs($this->admin, [ApiAbilities::ORDERS_READ]);

    withApiToken($token)->getJson(route('api.v1.products.index'))
        ->assertForbidden()
        ->assertJsonPath('message', __('Bu API anahtarının bu işlem için yetkisi yok.'));

    withApiToken($token)->getJson(route('api.v1.orders.index'))->assertOk();
});

test('tokens of passive, deleted or expired users stop working', function () {
    $token = apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]);
    withApiToken($token)->getJson(route('api.v1.products.index'))->assertOk();

    $this->admin->update(['status' => 'passive']);
    withApiToken($token)->getJson(route('api.v1.products.index'))->assertUnauthorized();

    $deleted = User::factory()->admin()->create();
    $deletedToken = apiAs($deleted, [ApiAbilities::PRODUCTS_READ]);
    $deleted->delete();
    withApiToken($deletedToken)->getJson(route('api.v1.products.index'))->assertUnauthorized();

    $active = User::factory()->admin()->create();
    $expired = $active->createToken('old', [ApiAbilities::PRODUCTS_READ], now()->subMinute())->plainTextToken;
    withApiToken($expired)->getJson(route('api.v1.products.index'))->assertUnauthorized();
});

test('member tokens are rejected and draft admins are read only', function () {
    $member = User::factory()->member()->create();
    withApiToken(apiAs($member, [ApiAbilities::PRODUCTS_READ]))->getJson(route('api.v1.products.index'))->assertUnauthorized();

    $draft = User::factory()->admin()->draft()->create();
    $token = apiAs($draft, [ApiAbilities::PRODUCTS_READ, ApiAbilities::PRODUCTS_WRITE]);
    apiProduct('DRF1');

    withApiToken($token)->getJson(route('api.v1.products.show', 'DRF1'))->assertOk();
    withApiToken($token)->patchJson(route('api.v1.products.update', 'DRF1'), ['price' => '1'])->assertForbidden();
});

test('the api rate limiter is configured per token', function () {
    expect(RateLimiter::limiter('api'))->not->toBeNull();

    config(['bayiyazilimi.api_rate_limit' => 2]);
    $token = apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]);

    withApiToken($token)->getJson(route('api.v1.products.index'))->assertOk()->assertHeader('X-RateLimit-Limit', '2');
    withApiToken($token)->getJson(route('api.v1.products.index'))->assertOk();
    withApiToken($token)->getJson(route('api.v1.products.index'))
        ->assertTooManyRequests()
        ->assertJsonPath('message', __('Çok fazla istek gönderildi; lütfen biraz bekleyip yeniden deneyin.'));

    // Başka anahtarın sınırı ayrıdır.
    withApiToken(apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]))->getJson(route('api.v1.products.index'))->assertOk();
});

test('unknown records return a json 404', function () {
    withApiToken(apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]))
        ->getJson(route('api.v1.products.show', 'YOK'))
        ->assertNotFound()
        ->assertJsonPath('message', __('Kayıt bulunamadı.'));
});

// ------------------------------------------------------------------ Ürünler

test('products are listed with prices as decimal strings and stock per warehouse', function () {
    $product = apiProduct('API1');
    app(StockService::class)->change($product, $this->warehouse, 7, StockMovementType::In);
    app(StockService::class)->change($product, $this->other, 3, StockMovementType::In);
    apiProduct('API2', ['status' => Status::Passive]);

    $token = apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]);

    $response = withApiToken($token)->getJson(route('api.v1.products.index', ['sku' => 'API1']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']])
        ->assertJsonPath('data.0.sku', 'API1')
        ->assertJsonPath('data.0.barcode', '869API1')
        ->assertJsonPath('data.0.prices.price', '100.5000')
        ->assertJsonPath('data.0.prices.compare_price', '120.0000')
        ->assertJsonPath('data.0.prices.tax_rate', '20.00')
        ->assertJsonPath('data.0.stock.total', 10)
        ->assertJsonPath('data.0.stock.sellable', 10)
        ->assertJsonPath('data.0.stock.warehouses.0.code', 'IZMIR')
        ->assertJsonPath('data.0.stock.warehouses.0.quantity', 3)
        ->assertJsonPath('data.0.stock.warehouses.1.quantity', 7);

    expect($response->json('data.0.updated_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');

    withApiToken($token)->getJson(route('api.v1.products.index', ['status' => 'passive', 'per_page' => 100]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonFragment(['sku' => 'API2']);

    withApiToken($token)->getJson(route('api.v1.products.index', ['per_page' => 101]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');

    withApiToken($token)->getJson(route('api.v1.products.show', 'API1'))
        ->assertOk()
        ->assertJsonPath('data.name', 'Ürün API1');
});

test('the updated_since filter returns only records changed after the given time', function () {
    $old = apiProduct('OLD1');
    $old->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
    apiProduct('NEW1');

    withApiToken(apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]))
        ->getJson(route('api.v1.products.index', ['updated_since' => now()->subDay()->toIso8601String(), 'per_page' => 100]))
        ->assertOk()
        ->assertJsonFragment(['sku' => 'NEW1'])
        ->assertJsonMissing(['sku' => 'OLD1']);
});

test('products are updated through the panel service and keep unsent fields', function () {
    apiProduct('UPD1');
    $token = apiAs($this->admin, [ApiAbilities::PRODUCTS_WRITE, ApiAbilities::PRODUCTS_READ]);

    withApiToken($token)->patchJson(route('api.v1.products.update', 'UPD1'), [
        'price' => 149.9,
        'status' => 'passive',
    ])
        ->assertOk()
        ->assertJsonPath('data.prices.price', '149.9000')
        ->assertJsonPath('data.prices.compare_price', '120.0000')
        ->assertJsonPath('data.status', 'passive');

    $product = Product::query()->where('sku', 'UPD1')->sole();
    expect($product->price)->toBeMoney('149.9000')
        ->and($product->name)->toBe('Ürün UPD1')
        ->and($product->status)->toBe(Status::Passive);

    withApiToken($token)->patchJson(route('api.v1.products.update', 'UPD1'), ['compare_price' => null])
        ->assertOk()
        ->assertJsonPath('data.prices.compare_price', null);

    withApiToken($token)->patchJson(route('api.v1.products.update', 'UPD1'), ['price' => -1, 'status' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price', 'status']);

    // Yalnız okuma yetkili anahtar yazamaz.
    withApiToken(apiAs($this->admin, [ApiAbilities::PRODUCTS_READ]))
        ->patchJson(route('api.v1.products.update', 'UPD1'), ['price' => 1])
        ->assertForbidden();
});

// ------------------------------------------------------------------ Stok

test('bulk stock sets absolute quantities as adjustment movements', function () {
    $a = apiProduct('STK1');
    $b = apiProduct('STK2');
    app(StockService::class)->change($a, $this->warehouse, 10, StockMovementType::In);

    withApiToken(apiAs($this->admin, [ApiAbilities::STOCK_WRITE]))->putJson(route('api.v1.stock.update'), [
        'items' => [
            ['sku' => 'STK1', 'warehouse' => 'MERKEZ', 'quantity' => 4],
            ['sku' => 'stk2', 'warehouse' => 'IZMIR', 'quantity' => 25],
            ['sku' => 'STK1', 'warehouse' => 'IZMIR', 'quantity' => 0],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('meta.count', 3)
        ->assertJsonPath('meta.changed', 2);

    $qty = fn (Product $p, Warehouse $w): int => (int) ProductStock::query()->where('product_id', $p->id)->where('warehouse_id', $w->id)->value('quantity');

    expect($qty($a, $this->warehouse))->toBe(4)
        ->and($qty($b, $this->other))->toBe(25)
        ->and($a->fresh()->stock)->toBe(4);

    $movement = StockMovement::query()->where('product_id', $a->id)->where('type', StockMovementType::Adjust->value)->sole();
    expect($movement->quantity)->toBe(-6)
        ->and($movement->note)->toBe('API')
        ->and($movement->user_id)->toBe($this->admin->id);
});

test('bulk stock is all or nothing', function () {
    $a = apiProduct('ALL1');
    apiProduct('LOT1', ['tracking' => StockTracking::Lot]);
    ShopSettings::save(['traceability_enabled' => true]);
    $token = apiAs($this->admin, [ApiAbilities::STOCK_WRITE]);

    $send = fn (array $items): TestResponse => withApiToken($token)->putJson(route('api.v1.stock.update'), ['items' => $items]);

    $send([
        ['sku' => 'ALL1', 'warehouse' => 'MERKEZ', 'quantity' => 5],
        ['sku' => 'YOK', 'warehouse' => 'MERKEZ', 'quantity' => 1],
        ['sku' => 'ALL1', 'warehouse' => 'YOKDEPO', 'quantity' => 1],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.sku', 'items.2.warehouse']);

    $send([
        ['sku' => 'ALL1', 'warehouse' => 'MERKEZ', 'quantity' => 5],
        ['sku' => 'ALL1', 'warehouse' => 'MERKEZ', 'quantity' => 6],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.sku']);

    $send([['sku' => 'ALL1', 'warehouse' => 'MERKEZ', 'quantity' => -1]])
        ->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);

    expect(StockMovement::query()->where('product_id', $a->id)->exists())->toBeFalse();

    // Lot / seri takipli ürün toplam miktarla yazılamaz.
    expect(Product::query()->where('sku', 'LOT1')->sole()->tracksLots())->toBeTrue();

    $send([
        ['sku' => 'ALL1', 'warehouse' => 'MERKEZ', 'quantity' => 5],
        ['sku' => 'LOT1', 'warehouse' => 'MERKEZ', 'quantity' => 5],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.sku']);

    expect(StockMovement::query()->where('product_id', $a->id)->exists())->toBeFalse();

    // Yazım sırasında bir satır iş kuralına takılırsa önceki satırlar da geri alınır.
    $b = apiProduct('ALL2');
    $this->app->instance(StockService::class, new class extends StockService
    {
        public function setQuantity(Product $product, Warehouse $warehouse, int $quantity, ?string $note = null, ?User $user = null, StockMovementType $type = StockMovementType::Adjust, ?WarehouseLocation $location = null, ?StockLot $lot = null): ?StockMovement
        {
            if ($product->sku === 'ALL2') {
                throw new ShopException('Test hatası');
            }

            return parent::setQuantity($product, $warehouse, $quantity, $note, $user, $type, $location, $lot);
        }
    });

    $send([
        ['sku' => 'ALL1', 'warehouse' => 'MERKEZ', 'quantity' => 5],
        ['sku' => 'ALL2', 'warehouse' => 'MERKEZ', 'quantity' => 5],
    ])->assertUnprocessable()->assertJsonPath('message', 'Test hatası');

    expect(StockMovement::query()->whereIn('product_id', [$a->id, $b->id])->exists())->toBeFalse()
        ->and(ProductStock::query()->where('product_id', $a->id)->sum('quantity'))->toEqual(0);
});

// ------------------------------------------------------------------ Siparişler

test('orders are listed and shown with lines, totals and payment info', function () {
    $account = Account::factory()->create(['code' => 'C-100', 'title' => 'Örnek Bayi', 'type' => CustomerType::Dealer]);
    apiOrder('API-1001', OrderStatus::Processing, $account);
    apiOrder('API-1002', OrderStatus::Pending);
    $token = apiAs($this->admin, [ApiAbilities::ORDERS_READ]);

    withApiToken($token)->getJson(route('api.v1.orders.index', ['status' => 'processing', 'per_page' => 100]))
        ->assertOk()
        ->assertJsonFragment(['number' => 'API-1001'])
        ->assertJsonMissing(['number' => 'API-1002']);

    withApiToken($token)->getJson(route('api.v1.orders.index', ['payment_status' => 'nope']))
        ->assertUnprocessable()->assertJsonValidationErrors('payment_status');

    withApiToken($token)->getJson(route('api.v1.orders.show', 'API-1001'))
        ->assertOk()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.account.code', 'C-100')
        ->assertJsonPath('data.customer.name', 'Ali Veli')
        ->assertJsonPath('data.billing_address.address', 'Merkez')
        ->assertJsonPath('data.lines.0.sku', 'LINE-1')
        ->assertJsonPath('data.lines.0.quantity', 2)
        ->assertJsonPath('data.lines.0.unit_price', '50.0000')
        ->assertJsonPath('data.lines.0.line_total', '120.00')
        ->assertJsonPath('data.totals.grand_total', '120.00')
        ->assertJsonPath('data.payment.status', 'pending')
        ->assertJsonPath('data.invoice.number', null);
});

test('order status changes go through the state machine', function () {
    $carrier = Carrier::query()->create(['code' => 'API-KARGO', 'name' => 'API Kargo', 'status' => Status::Active, 'tracking_url' => 'https://kargo.example/{code}']);
    $order = apiOrder('API-2001', OrderStatus::Processing);
    $token = apiAs($this->admin, [ApiAbilities::ORDERS_WRITE]);

    withApiToken($token)->patchJson(route('api.v1.orders.status', 'API-2001'), [
        'status' => 'shipped',
        'carrier' => 'API-KARGO',
        'tracking_number' => 'TRK123',
        'note' => 'ERP sevk etti',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.shipping.carrier', 'API-KARGO')
        ->assertJsonPath('data.shipping.tracking_number', 'TRK123')
        ->assertJsonPath('data.shipping.tracking_url', 'https://kargo.example/TRK123');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($order->fresh()->carrier_id)->toBe($carrier->id)
        ->and($order->histories()->where('note', 'ERP sevk etti')->where('user_id', $this->admin->id)->exists())->toBeTrue();

    // Kargodaki sipariş iptal edilemez: 422 + Türkçe mesaj, durum değişmez.
    withApiToken($token)->patchJson(route('api.v1.orders.status', 'API-2001'), ['status' => 'cancelled'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('Kargoya verilmiş / tamamlanmış sipariş iptal edilemez; ürünlerin geri dönüşü için iade talebi oluşturun.'));

    apiOrder('API-2002', OrderStatus::Pending);
    withApiToken($token)->patchJson(route('api.v1.orders.status', 'API-2002'), ['status' => 'completed'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __(':from durumundaki sipariş :to yapılamaz.', ['from' => OrderStatus::Pending->label(), 'to' => OrderStatus::Completed->label()]));

    withApiToken($token)->patchJson(route('api.v1.orders.status', 'API-2002'), ['status' => 'bogus', 'carrier' => 'YOK'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'carrier']);

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($order->fresh()->tracking_number)->toBe('TRK123');
});

test('invoice numbers are recorded only on completed orders', function () {
    $order = apiOrder('API-3001', OrderStatus::Completed);
    apiOrder('API-3002', OrderStatus::Processing);
    $token = apiAs($this->admin, [ApiAbilities::ORDERS_WRITE]);

    withApiToken($token)->putJson(route('api.v1.orders.invoice', 'API-3001'), ['invoice_number' => 'GIB2026000001'])
        ->assertOk()
        ->assertJsonPath('data.invoice.number', 'GIB2026000001');

    expect($order->fresh()->invoice_number)->toBe('GIB2026000001')
        ->and($order->fresh()->invoiced_at)->not->toBeNull();

    withApiToken($token)->putJson(route('api.v1.orders.invoice', 'API-3002'), ['invoice_number' => 'X1'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('Fatura bilgisi yalnızca tamamlanan siparişlere girilebilir.'));

    withApiToken($token)->putJson(route('api.v1.orders.invoice', 'API-3001'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoice_number');

    withApiToken($token)->putJson(route('api.v1.orders.invoice', 'API-3001'), ['invoice_number' => null])
        ->assertOk()
        ->assertJsonPath('data.invoice.number', null);
});

// ------------------------------------------------------------------ Cari hesaplar

test('accounts are listed with balances and shown with credit info', function () {
    $dealer = Account::factory()->create(['code' => 'B-200', 'title' => 'Bayi A', 'type' => CustomerType::Dealer, 'payment_terms' => 'credit', 'credit_limit' => '1000', 'payment_term_days' => 45]);
    Account::factory()->create(['code' => 'M-300', 'title' => 'Müşteri B', 'type' => CustomerType::Customer]);
    app(LedgerService::class)->record($dealer, TransactionType::Adjustment, TransactionDirection::Debit, '250.40', ['description' => 'Açılış']);
    $token = apiAs($this->admin, [ApiAbilities::ACCOUNTS_READ]);

    withApiToken($token)->getJson(route('api.v1.accounts.index', ['type' => 'dealer', 'per_page' => 100]))
        ->assertOk()
        ->assertJsonFragment(['code' => 'B-200', 'balance' => '250.40'])
        ->assertJsonMissing(['code' => 'M-300']);

    withApiToken($token)->getJson(route('api.v1.accounts.show', 'B-200'))
        ->assertOk()
        ->assertJsonPath('data.balance', '250.40')
        ->assertJsonPath('data.credit.payment_terms', 'credit')
        ->assertJsonPath('data.credit.limit', '1000.00')
        ->assertJsonPath('data.credit.term_days', 45)
        ->assertJsonPath('data.credit.risk', '250.40')
        ->assertJsonPath('data.credit.available', '749.60');
});

test('ledger transactions from the erp are idempotent by reference', function () {
    $account = Account::factory()->create(['code' => 'L-400', 'type' => CustomerType::Dealer]);
    $token = apiAs($this->admin, [ApiAbilities::LEDGER_WRITE, ApiAbilities::ACCOUNTS_READ]);

    $payload = ['type' => 'debit', 'amount' => '500.00', 'reference' => 'ERP-FAT-1', 'date' => '2026-11-01', 'due_date' => '2026-12-01', 'description' => 'ERP faturası'];

    $first = withApiToken($token)->postJson(route('api.v1.accounts.transactions.store', 'L-400'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.reference', 'ERP-FAT-1')
        ->assertJsonPath('data.direction', 'debit')
        ->assertJsonPath('data.amount', '500.00')
        ->assertJsonPath('data.due_date', '2026-12-01');

    withApiToken($token)->postJson(route('api.v1.accounts.transactions.store', 'L-400'), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    withApiToken($token)->postJson(route('api.v1.accounts.transactions.store', 'L-400'), ['type' => 'collection', 'amount' => 120.5, 'reference' => 'ERP-TAH-1'])
        ->assertCreated()
        ->assertJsonPath('data.type', TransactionType::Payment->value)
        ->assertJsonPath('data.direction', 'credit');

    expect(AccountTransaction::query()->withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(2)
        ->and((string) app(LedgerService::class)->balance($account->id))->toBe('379.50');

    withApiToken($token)->getJson(route('api.v1.accounts.show', 'L-400'))->assertJsonPath('data.balance', '379.50');

    // Aynı referans başka cari hesapta kullanılamaz.
    Account::factory()->create(['code' => 'L-401']);
    withApiToken($token)->postJson(route('api.v1.accounts.transactions.store', 'L-401'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', __('Bu referans başka bir cari hesabın hareketinde kullanılmış.'));

    withApiToken($token)->postJson(route('api.v1.accounts.transactions.store', 'L-400'), ['type' => 'gift', 'amount' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'amount', 'reference']);
});

test('external collections can be reversed from the panel', function () {
    $account = Account::factory()->create(['code' => 'R-500', 'type' => CustomerType::Dealer]);

    withApiToken(apiAs($this->admin, [ApiAbilities::LEDGER_WRITE]))
        ->postJson(route('api.v1.accounts.transactions.store', 'R-500'), ['type' => 'collection', 'amount' => '75', 'reference' => 'ERP-TAH-9'])
        ->assertCreated();

    $transaction = AccountTransaction::query()->withoutGlobalScopes()->where('external_ref', 'ERP-TAH-9')->sole();
    expect($transaction->isExternal())->toBeTrue();

    app(CollectionService::class)->reverse($transaction->load('source'), $this->admin);

    expect((string) app(LedgerService::class)->balance($account->id))->toBe('0.00');
});
