<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Shop\StockMovementType;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\Currency;
use App\Models\LocationStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\OrderService;
use App\Services\Shop\OrderStockService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function traceProduct(string $sku, StockTracking $tracking = StockTracking::None, bool $expiry = false): Product
{
    return Product::query()->create([
        'sku' => $sku, 'name' => "Ürün {$sku}", 'slug' => strtolower($sku), 'price' => 100,
        'currency_id' => Currency::query()->where('code', 'TRY')->value('id'), 'tax_rate' => 20, 'sale_mode' => 'cart',
        'unit' => 'Adet', 'status' => Status::Active, 'tracking' => $tracking, 'expiry_tracking' => $expiry,
    ])->fresh();
}

function traceOrder(Product $product, int $qty, Warehouse $warehouse): Order
{
    $order = Order::query()->create([
        'number' => 'S-TR-'.fake()->unique()->numerify('####'), 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali', 'email' => 'a@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali'], 'currency_code' => 'TRY',
        'subtotal' => 100, 'tax_total' => 20, 'grand_total' => 120, 'warehouse_id' => $warehouse->id,
    ]);
    $order->items()->create(['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'quantity' => $qty, 'unit_price' => 100, 'tax_rate' => 20, 'tax_amount' => 20, 'line_total' => 120]);
    app(OrderStockService::class)->deductForOrder($order);

    return $order->fresh();
}

function lotQty(StockLot $lot): int
{
    return (int) LocationStock::query()->where('stock_lot_id', $lot->id)->sum('quantity');
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();

    Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2, 'thousands_separator' => '.',
        'decimal_separator' => ',', 'exchange_rate' => 1, 'rate_source' => RateSource::Manual,
        'status' => Status::Active, 'sort_order' => 1,
    ]);

    Warehouse::query()->update(['is_default' => false]);
    $this->warehouse = Warehouse::query()->create(['code' => 'TRC', 'name' => 'İzleme Depo', 'is_default' => true, 'is_sellable' => true, 'status' => Status::Active]);
    $this->other = Warehouse::query()->create(['code' => 'TRC2', 'name' => 'İkinci Depo', 'is_default' => false, 'is_sellable' => true, 'status' => Status::Active]);
    $this->supplier = Supplier::query()->create(['name' => 'Tedarik A.Ş.', 'status' => Status::Active]);
    $this->stock = app(StockService::class);

    // Lot / seri / SKT takibi isteğe bağlıdır; bu testler açık hâlini doğrular.
    ShopSettings::save(['traceability_enabled' => true]);
});

test('with traceability switched off every product behaves untracked and keeps its setting', function () {
    $product = traceProduct('OFF-1', StockTracking::Lot, expiry: true);
    ShopSettings::save(['traceability_enabled' => false]);

    expect($product->fresh()->tracksLots())->toBeFalse()
        ->and($product->fresh()->tracking)->toBe(StockTracking::Lot);

    // Lot / SKT istenmez; stok lot belirsiz satıra girer.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id, 'operation' => 'in', 'warehouse_id' => $this->warehouse->id, 'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->stock)->toBe(4)
        ->and(StockLot::query()->where('product_id', $product->id)->exists())->toBeFalse();

    $this->actingAs($this->admin)->get(route('panel.shop.products.edit', $product->id))
        ->assertInertia(fn (Assert $p) => $p->where('defaults.traceability', false)->where('product.tracking', 'none'));

    $this->actingAs($this->admin)->get(route('panel.shop.stock-settings.edit'))
        ->assertInertia(fn (Assert $p) => $p->where('traceability', false));
});

test('a lotted receipt automatically settles orders that were sold short', function () {
    $product = traceProduct('PRE-1', StockTracking::Lot);
    $product->update(['allow_backorder' => true]);

    // Stok yokken (ön sipariş) satılan 3 adet "lot belirsiz" olarak eksiye düşer.
    $order = traceOrder($product->fresh(), 3, $this->warehouse);
    expect(app(StockService::class)->orderPicks($order)->first()['lot'])->toBeNull()
        ->and($product->fresh()->stock)->toBe(-3);

    // Bir de sipariş dışı eksik çıkış.
    $this->stock->change($product, $this->warehouse, -1, StockMovementType::Out);

    $this->actingAs($this->admin)->post(route('panel.shop.stock-receipts.store'), [
        'supplier_id' => $this->supplier->id, 'document_type' => 'waybill', 'document_no' => 'IRS-PRE',
        'document_date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id,
        'lines' => [['product_id' => $product->id, 'quantity' => 10, 'lot_code' => 'GEL1']],
    ])->assertSessionHasNoErrors();

    $lot = StockLot::query()->where('code', 'GEL1')->firstOrFail();

    // Sipariş kendiliğinden yeni lota bağlandı; lot belirsiz eksi kalmadı.
    expect(app(StockService::class)->orderPicks($order)->pluck('quantity', 'lot.code')->all())->toBe(['GEL1' => 3])
        ->and(lotQty($lot))->toBe(6)
        ->and((int) LocationStock::query()->where('product_id', $product->id)->whereNull('stock_lot_id')->where('quantity', '!=', 0)->count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(6);

    // İptal stoğu doğru lota döndürür.
    app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Cancelled, [], $this->admin);
    expect(lotQty($lot))->toBe(9);
});

test('suppliers are managed from definitions and cannot be deleted once used', function () {
    $this->actingAs($this->admin)->post(route('panel.shop.suppliers.store'), [
        'name' => 'Yeni Tedarikçi', 'tax_number' => '1234567890', 'email' => 'satis@tedarik.test',
    ])->assertSessionHasNoErrors();

    $supplier = Supplier::query()->where('name', 'Yeni Tedarikçi')->firstOrFail();
    expect($supplier->isActive())->toBeTrue();

    $this->actingAs($this->admin)->get(route('panel.shop.suppliers.index'))
        ->assertInertia(fn (Assert $p) => $p->component('panel/tools/definitions/suppliers/index')->has('suppliers', 2));

    StockReceipt::query()->create([
        'number' => 'MK-1', 'supplier_id' => $supplier->id, 'document_type' => 'waybill', 'document_no' => 'X1',
        'document_date' => now(), 'warehouse_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->admin)->delete(route('panel.shop.suppliers.destroy', $supplier->id))->assertSessionHas('toast.type', 'warning');
    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeTrue();
});

test('a stock receipt enters plain, lot and serial lines with the supplier document', function () {
    $plain = traceProduct('PLN-1');
    $lotted = traceProduct('LOT-1', StockTracking::Lot, expiry: true);
    $serial = traceProduct('SER-1', StockTracking::Serial);
    $expires = CarbonImmutable::today()->addMonths(6)->toDateString();

    $this->actingAs($this->admin)->post(route('panel.shop.stock-receipts.store'), [
        'supplier_id' => $this->supplier->id, 'document_type' => 'invoice', 'document_no' => 'FTR-2026-1',
        'document_date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id,
        'lines' => [
            ['product_id' => $plain->id, 'quantity' => 5],
            ['product_id' => $lotted->id, 'quantity' => 10, 'lot_code' => 'l2611', 'expires_at' => $expires],
            ['product_id' => $serial->id, 'quantity' => 3, 'serials' => "SN-1\nSN-2\nSN-3"],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $receipt = StockReceipt::query()->where('document_no', 'FTR-2026-1')->firstOrFail();
    $lot = StockLot::query()->where('product_id', $lotted->id)->firstOrFail();

    expect($receipt->movements()->count())->toBe(5)
        ->and($plain->fresh()->stock)->toBe(5)
        ->and($lot->code)->toBe('L2611')
        ->and($lot->expires_at->toDateString())->toBe($expires)
        ->and(lotQty($lot))->toBe(10)
        ->and($serial->fresh()->stock)->toBe(3)
        ->and(StockLot::query()->where('product_id', $serial->id)->pluck('code')->sort()->values()->all())->toBe(['SN-1', 'SN-2', 'SN-3'])
        ->and(StockMovement::query()->where('reference_id', $receipt->id)->where('type', StockMovementType::In->value)->count())->toBe(5);

    $this->actingAs($this->admin)->get(route('panel.shop.stock-receipts.show', $receipt->id))
        ->assertInertia(fn (Assert $p) => $p->component('panel/shop/stock-receipts/show')->has('lines', 5)->where('receipt.supplier.name', 'Tedarik A.Ş.'));
    $this->actingAs($this->admin)->get(route('panel.shop.stock-receipts.index'))
        ->assertInertia(fn (Assert $p) => $p->has('receipts.data', 1)->where('receipts.data.0.quantity', 18));

    // Aynı tedarikçinin aynı belgesi ikinci kez kabul edilemez.
    $this->actingAs($this->admin)->post(route('panel.shop.stock-receipts.store'), [
        'supplier_id' => $this->supplier->id, 'document_type' => 'invoice', 'document_no' => 'FTR-2026-1',
        'document_date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id,
        'lines' => [['product_id' => $plain->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('document_no');
});

test('receipt lines of tracked products are validated', function () {
    $lotted = traceProduct('LOT-2', StockTracking::Lot, expiry: true);
    $serial = traceProduct('SER-2', StockTracking::Serial);
    $base = [
        'supplier_id' => $this->supplier->id, 'document_type' => 'waybill', 'document_no' => 'IRS-9',
        'document_date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id,
    ];

    $this->actingAs($this->admin)->post(route('panel.shop.stock-receipts.store'), [...$base, 'lines' => [
        ['product_id' => $lotted->id, 'quantity' => 2],
        ['product_id' => $serial->id, 'quantity' => 2, 'serials' => 'A1'],
    ]])->assertSessionHasErrors(['lines.0.lot_code', 'lines.0.expires_at', 'lines.1.serials']);

    $this->stock->change($serial, $this->warehouse, 1, StockMovementType::In, lot: StockLot::query()->create(['product_id' => $serial->id, 'code' => 'A1']));

    $this->actingAs($this->admin)->post(route('panel.shop.stock-receipts.store'), [...$base, 'lines' => [
        ['product_id' => $serial->id, 'quantity' => 2, 'serials' => 'A1, A2'],
    ]])->assertSessionHasErrors('lines.0.serials');

    expect(StockReceipt::query()->count())->toBe(0);
});

test('orders pick the lot expiring first and never sell expired lots', function () {
    $product = traceProduct('FEFO-1', StockTracking::Lot, expiry: true);
    $late = StockLot::query()->create(['product_id' => $product->id, 'code' => 'LATE', 'expires_at' => now()->addYear()]);
    $soon = StockLot::query()->create(['product_id' => $product->id, 'code' => 'SOON', 'expires_at' => now()->addMonth()]);
    $expired = StockLot::query()->create(['product_id' => $product->id, 'code' => 'OLD', 'expires_at' => now()->subDay()]);

    foreach ([$late, $soon, $expired] as $lot) {
        $this->stock->change($product, $this->warehouse, 5, StockMovementType::In, lot: $lot);
    }

    // Süresi geçmiş lot satılabilir stoğa sayılmaz.
    expect($product->fresh()->stock)->toBe(10);

    $order = traceOrder($product, 7, $this->warehouse);

    expect(lotQty($soon))->toBe(0)
        ->and(lotQty($late))->toBe(3)
        ->and(lotQty($expired))->toBe(5)
        ->and(app(StockService::class)->orderPicks($order)->pluck('quantity', 'lot.code')->all())->toBe(['SOON' => 5, 'LATE' => 2]);

    $this->actingAs($this->admin)->get(route('panel.shop.orders.show', $order->id))
        ->assertInertia(fn (Assert $p) => $p->where('order.items.0.picks', fn ($picks) => collect($picks)->pluck('lot')->sort()->values()->all() === ['LATE', 'SOON']));
});

test('a lot expiring overnight leaves the sellable stock via the nightly command', function () {
    $product = traceProduct('EXP-1', StockTracking::Lot, expiry: true);
    $lot = StockLot::query()->create(['product_id' => $product->id, 'code' => 'L1', 'expires_at' => now()->addDay()]);
    $this->stock->change($product, $this->warehouse, 4, StockMovementType::In, lot: $lot);

    expect($product->fresh()->stock)->toBe(4);

    $this->travelTo(now()->addDays(2));
    $this->artisan('shop:refresh-expired-stock')->assertSuccessful();

    expect($product->fresh()->stock)->toBe(0);
});

test('serial numbers are picked one by one and cannot be stocked twice', function () {
    $product = traceProduct('SN-PRD', StockTracking::Serial);

    foreach (['S1', 'S2', 'S3'] as $code) {
        $this->stock->change($product, $this->warehouse, 1, StockMovementType::In, lot: StockLot::query()->create(['product_id' => $product->id, 'code' => $code]));
    }

    $order = traceOrder($product, 2, $this->warehouse);

    expect(app(StockService::class)->orderPicks($order)->pluck('quantity')->all())->toBe([1, 1])
        ->and($product->fresh()->stock)->toBe(1);

    $s3 = StockLot::query()->where('code', 'S3')->firstOrFail();
    expect(fn () => $this->stock->change($product, $this->warehouse, 1, StockMovementType::In, lot: $s3))
        ->toThrow(ShopException::class);
});

test('a picked lot can be changed on the order and cancellation restores the right lots', function () {
    $product = traceProduct('CHG-1', StockTracking::Lot);
    $a = StockLot::query()->create(['product_id' => $product->id, 'code' => 'A', 'expires_at' => now()->addMonth()]);
    $b = StockLot::query()->create(['product_id' => $product->id, 'code' => 'B', 'expires_at' => now()->addYear()]);
    $this->stock->change($product, $this->warehouse, 5, StockMovementType::In, lot: $a);
    $this->stock->change($product, $this->warehouse, 5, StockMovementType::In, lot: $b);

    $order = traceOrder($product, 3, $this->warehouse);
    $pick = app(StockService::class)->orderPicks($order)->first();
    expect($pick['lot']->code)->toBe('A');

    $this->actingAs($this->admin)->post(route('panel.shop.orders.change-lot', $order->id), [
        'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'location_id' => $pick['location']->id,
        'from_lot_id' => $a->id, 'to_lot_id' => $b->id, 'quantity' => 2,
    ])->assertSessionHasNoErrors();

    expect(lotQty($a))->toBe(4)
        ->and(lotQty($b))->toBe(3)
        ->and(app(StockService::class)->orderPicks($order)->pluck('quantity', 'lot.code')->sortKeys()->all())->toBe(['A' => 1, 'B' => 2])
        ->and($product->fresh()->stock)->toBe(7);

    app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled, [], $this->admin);

    expect(lotQty($a))->toBe(5)->and(lotQty($b))->toBe(5)->and($product->fresh()->stock)->toBe(10);
});

test('cancel, reopen and cancel again does not restore stock twice', function () {
    $product = traceProduct('DBL-1');
    $this->stock->change($product, $this->warehouse, 10, StockMovementType::In);
    $order = traceOrder($product, 4, $this->warehouse);
    $orders = app(OrderService::class);

    $orders->changeStatus($order, OrderStatus::Cancelled, [], $this->admin);
    $orders->changeStatus($order->fresh(), OrderStatus::Processing, [], $this->admin);
    expect($product->fresh()->stock)->toBe(6);

    $orders->changeStatus($order->fresh(), OrderStatus::Cancelled, [], $this->admin);
    expect($product->fresh()->stock)->toBe(10);
});

test('quick operations on tracked products require and carry lots', function () {
    $product = traceProduct('OPS-1', StockTracking::Lot, expiry: true);

    // Giriş: lot no ve SKT zorunlu.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id, 'operation' => 'in', 'warehouse_id' => $this->warehouse->id, 'quantity' => 3,
    ])->assertSessionHasErrors(['lot_code', 'expires_at']);

    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id, 'operation' => 'in', 'warehouse_id' => $this->warehouse->id, 'quantity' => 6,
        'lot_code' => 'Q1', 'expires_at' => now()->addMonths(3)->toDateString(),
    ])->assertSessionHasNoErrors();

    $lot = StockLot::query()->where('product_id', $product->id)->where('code', 'Q1')->firstOrFail();

    // Sayım lot seçmeden yapılamaz.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $product->id, 'operation' => 'count', 'warehouse_id' => $this->warehouse->id, 'quantity' => 2,
    ])->assertSessionHasErrors('lot_id');

    // Transfer lotu hedef depoya aynen taşır.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.transfer'), [
        'product_id' => $product->id, 'from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->other->id, 'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect((int) LocationStock::query()->where('stock_lot_id', $lot->id)->where('warehouse_id', $this->other->id)->sum('quantity'))->toBe(4)
        ->and((int) LocationStock::query()->where('product_id', $product->id)->where('warehouse_id', $this->other->id)->whereNull('stock_lot_id')->sum('quantity'))->toBe(0);
});

test('returns go back to the lot the order was picked from', function () {
    $product = traceProduct('RET-1', StockTracking::Lot);
    $a = StockLot::query()->create(['product_id' => $product->id, 'code' => 'RA', 'expires_at' => now()->addMonth()]);
    $b = StockLot::query()->create(['product_id' => $product->id, 'code' => 'RB', 'expires_at' => now()->addYear()]);
    $this->stock->change($product, $this->warehouse, 2, StockMovementType::In, lot: $a);
    $this->stock->change($product, $this->warehouse, 5, StockMovementType::In, lot: $b);

    $order = traceOrder($product, 4, $this->warehouse);
    expect(lotQty($a))->toBe(0)->and(lotQty($b))->toBe(3);

    app(OrderStockService::class)->restockFromOrder($order, $product, 3, $this->warehouse, 'İade');

    expect(lotQty($a))->toBe(2)->and(lotQty($b))->toBe(4);
});

test('lots screen and movement trace show where a lot went', function () {
    $product = traceProduct('TRC-1', StockTracking::Lot, expiry: true);
    $lot = StockLot::query()->create(['product_id' => $product->id, 'code' => 'TRACE', 'expires_at' => now()->addDays(10)]);
    $this->stock->change($product, $this->warehouse, 5, StockMovementType::In, lot: $lot);
    $order = traceOrder($product, 2, $this->warehouse);

    $this->actingAs($this->admin)->get(route('panel.shop.stock.lots', ['state' => 'expiring']))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('panel/shop/stock/lots')
            ->has('lots.data', 1)
            ->where('lots.data.0.code', 'TRACE')
            ->where('lots.data.0.quantity', 3)
            ->where('lots.data.0.expiring', true));

    $this->actingAs($this->admin)->get(route('panel.shop.stock.movements', ['lot' => $lot->id]))
        ->assertInertia(fn (Assert $p) => $p->has('movements.data', 2)
            ->where('lot.code', 'TRACE')
            ->where('movements.data', fn ($rows) => collect($rows)->contains('reference', $order->number)));

    $this->actingAs($this->admin)->get(route('panel.shop.stock.index', ['stock' => 'expiring']))
        ->assertInertia(fn (Assert $p) => $p->has('products.data', 1)->where('products.data.0.tracking', 'lot'));
});
