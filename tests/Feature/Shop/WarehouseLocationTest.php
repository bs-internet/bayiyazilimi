<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Currency;
use App\Models\LocationStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Shop\OrderStockService;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function locationQty(Product $product, WarehouseLocation $location): int
{
    return (int) LocationStock::query()->where('product_id', $product->id)->where('warehouse_location_id', $location->id)->value('quantity');
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();

    $currency = Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2, 'thousands_separator' => '.',
        'decimal_separator' => ',', 'exchange_rate' => 1, 'rate_source' => RateSource::Manual,
        'status' => Status::Active, 'sort_order' => 1,
    ]);

    Warehouse::query()->update(['is_default' => false]);
    $this->warehouse = Warehouse::query()->create(['code' => 'LOC', 'name' => 'Lokasyon Depo', 'is_default' => true, 'is_sellable' => true, 'status' => Status::Active]);
    $this->other = Warehouse::query()->create(['code' => 'LOC2', 'name' => 'İkinci Depo', 'is_default' => false, 'is_sellable' => true, 'status' => Status::Active]);

    $this->product = Product::query()->create([
        'sku' => 'LOC-1', 'name' => 'Lokasyon Ürün', 'slug' => 'lokasyon-urun', 'price' => 100,
        'currency_id' => $currency->id, 'tax_rate' => 20, 'sale_mode' => 'cart', 'unit' => 'Adet', 'status' => Status::Active,
    ]);
});

test('every warehouse gets an unassigned location and bulk creation builds aisle-rack-bin codes', function () {
    expect($this->warehouse->locations()->where('is_unassigned', true)->count())->toBe(1);

    $this->actingAs($this->admin)->post(route('panel.shop.warehouse-locations.bulk', $this->warehouse->id), [
        'aisles' => 'A, B', 'rack_from' => 1, 'rack_to' => 10, 'bin_from' => 1, 'bin_to' => 2,
    ])->assertSessionHasNoErrors();

    $codes = $this->warehouse->locations()->where('is_unassigned', false)->pluck('code');
    expect($codes)->toHaveCount(40)->and($codes)->toContain('A-01-1', 'B-10-2');

    // Var olanlar atlanır; aynı kod tekli de eklenemez.
    $this->actingAs($this->admin)->post(route('panel.shop.warehouse-locations.bulk', $this->warehouse->id), [
        'aisles' => 'A', 'rack_from' => 1, 'rack_to' => 1, 'bin_from' => 1, 'bin_to' => 3,
    ]);
    expect($this->warehouse->locations()->where('is_unassigned', false)->count())->toBe(41);

    $this->actingAs($this->admin)->post(route('panel.shop.warehouse-locations.store', $this->warehouse->id), ['aisle' => 'a', 'rack' => '01', 'bin' => '1']);
    expect($this->warehouse->locations()->where('code', 'A-01-1')->count())->toBe(1);
});

test('stock goes into chosen location, out automatically in pick order, and can be relocated', function () {
    $stock = app(StockService::class);
    $a1 = $this->warehouse->locations()->create(['code' => 'A-01', 'aisle' => 'A', 'rack' => '01', 'status' => Status::Active, 'sort_order' => 1]);
    $b1 = $this->warehouse->locations()->create(['code' => 'B-01', 'aisle' => 'B', 'rack' => '01', 'status' => Status::Active, 'sort_order' => 2]);
    $unassigned = $this->warehouse->unassignedLocation();

    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $this->product->id, 'operation' => 'in', 'warehouse_id' => $this->warehouse->id,
        'location_id' => $b1->id, 'quantity' => 5,
    ])->assertSessionHasNoErrors();

    // Lokasyonsuz giriş: ürünün ana lokasyonuna (B-01).
    $stock->change($this->product, $this->warehouse, 3, StockMovementType::In);
    expect(locationQty($this->product, $b1))->toBe(8);

    $stock->change($this->product, $this->warehouse, 2, StockMovementType::In, null, null, null, true, $a1);

    // Çıkış toplama sırasıyla: önce A-01 (2), sonra B-01.
    $stock->change($this->product, $this->warehouse, -4, StockMovementType::Out);
    expect(locationQty($this->product, $a1))->toBe(0)
        ->and(locationQty($this->product, $b1))->toBe(6)
        ->and((int) ProductStock::query()->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->value('quantity'))->toBe(6);

    // Lokasyon değişimi depo toplamını değiştirmez.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.relocate'), [
        'product_id' => $this->product->id, 'from_location_id' => $b1->id, 'to_location_id' => $a1->id, 'quantity' => 4,
    ])->assertSessionHasNoErrors();
    expect(locationQty($this->product, $a1))->toBe(4)
        ->and(locationQty($this->product, $b1))->toBe(2)
        ->and($this->product->fresh()->stock)->toBe(6);

    // Kaynakta olmayan miktar taşınamaz.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.relocate'), [
        'product_id' => $this->product->id, 'from_location_id' => $b1->id, 'to_location_id' => $a1->id, 'quantity' => 50,
    ]);
    expect(locationQty($this->product, $b1))->toBe(2);

    // Yetmeyen çıkış Atanmamış'ta eksiye düşer.
    $stock->change($this->product, $this->warehouse, -10, StockMovementType::Out);
    expect(locationQty($this->product, $unassigned))->toBe(-4);

    // Başka deponun lokasyonu kullanılamaz.
    $foreign = $this->other->unassignedLocation();
    $this->actingAs($this->admin)->post(route('panel.shop.stock.adjust'), [
        'product_id' => $this->product->id, 'operation' => 'in', 'warehouse_id' => $this->warehouse->id,
        'location_id' => $foreign->id, 'quantity' => 1,
    ])->assertSessionHasErrors('location_id');
});

test('transfer can take stock from a chosen source location', function () {
    $stock = app(StockService::class);
    $a1 = $this->warehouse->locations()->create(['code' => 'A-01', 'aisle' => 'A', 'rack' => '01', 'status' => Status::Active, 'sort_order' => 1]);
    $b1 = $this->warehouse->locations()->create(['code' => 'B-01', 'aisle' => 'B', 'rack' => '01', 'status' => Status::Active, 'sort_order' => 2]);
    $target = $this->other->locations()->create(['code' => 'C-01', 'aisle' => 'C', 'rack' => '01', 'status' => Status::Active, 'sort_order' => 1]);

    $stock->change($this->product, $this->warehouse, 3, StockMovementType::In, null, null, null, true, $a1);
    $stock->change($this->product, $this->warehouse, 5, StockMovementType::In, null, null, null, true, $b1);

    // Toplama sırasına göre A-01'den düşerdi; B-01 seçildiği için oradan düşer.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.transfer'), [
        'product_id' => $this->product->id, 'from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->other->id,
        'from_location_id' => $b1->id, 'to_location_id' => $target->id, 'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect(locationQty($this->product, $a1))->toBe(3)
        ->and(locationQty($this->product, $b1))->toBe(1)
        ->and(locationQty($this->product, $target))->toBe(4)
        ->and($this->product->fresh()->stock)->toBe(8);

    // Seçilen lokasyonda yetmeyen miktar aktarılmaz (depo toplamı yetse bile).
    $this->actingAs($this->admin)->post(route('panel.shop.stock.transfer'), [
        'product_id' => $this->product->id, 'from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->other->id,
        'from_location_id' => $b1->id, 'quantity' => 2,
    ])->assertSessionHas('toast.type', 'warning');
    expect(locationQty($this->product, $b1))->toBe(1);

    // Kaynak lokasyon kaynak depoya ait olmalı.
    $this->actingAs($this->admin)->post(route('panel.shop.stock.transfer'), [
        'product_id' => $this->product->id, 'from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->other->id,
        'from_location_id' => $target->id, 'quantity' => 1,
    ])->assertSessionHasErrors('from_location_id');
});

test('orders deduct from locations automatically and cancellation restores them', function () {
    $stock = app(StockService::class);
    $a1 = $this->warehouse->locations()->create(['code' => 'A-01', 'status' => Status::Active, 'sort_order' => 1]);
    $stock->change($this->product, $this->warehouse, 2, StockMovementType::In, null, null, null, true, $a1);
    $stock->change($this->product, $this->warehouse, 5, StockMovementType::In, null, null, null, true, $this->warehouse->unassignedLocation());

    $order = Order::query()->create([
        'number' => 'S-LOC-1', 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali', 'email' => 'a@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali'], 'currency_code' => 'TRY',
        'subtotal' => 300, 'tax_total' => 60, 'grand_total' => 360, 'warehouse_id' => $this->warehouse->id,
    ]);
    $order->items()->create(['product_id' => $this->product->id, 'sku' => 'LOC-1', 'name' => 'x', 'quantity' => 3, 'unit_price' => 100, 'tax_rate' => 20, 'tax_amount' => 60, 'line_total' => 360]);

    app(OrderStockService::class)->deductForOrder($order);

    expect(locationQty($this->product, $a1))->toBe(0)
        ->and(locationQty($this->product, $this->warehouse->unassignedLocation()))->toBe(4)
        ->and(StockMovement::query()->where('reference_id', $order->id)->count())->toBe(2);

    $this->actingAs($this->admin)->get(route('panel.shop.orders.show', $order->id))
        ->assertInertia(fn (Assert $p) => $p->where('order.items.0.picks', fn ($picks) => collect($picks)->pluck('location')->all() === ['A-01', 'Atanmamış']));

    app(OrderStockService::class)->restoreForOrder($order->fresh());
    expect(locationQty($this->product, $a1))->toBe(2)
        ->and(locationQty($this->product, $this->warehouse->unassignedLocation()))->toBe(5);
});

test('returned items go back to the locations the order was picked from', function () {
    $stock = app(StockService::class);
    $a1 = $this->warehouse->locations()->create(['code' => 'A-01', 'status' => Status::Active, 'sort_order' => 1]);
    $b1 = $this->warehouse->locations()->create(['code' => 'B-01', 'status' => Status::Active, 'sort_order' => 2]);
    $stock->change($this->product, $this->warehouse, 1, StockMovementType::In, null, null, null, true, $a1);
    $stock->change($this->product, $this->warehouse, 5, StockMovementType::In, null, null, null, true, $b1);

    $order = Order::query()->create([
        'number' => 'S-LOC-2', 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali', 'email' => 'a@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali'], 'currency_code' => 'TRY',
        'subtotal' => 300, 'tax_total' => 60, 'grand_total' => 360, 'warehouse_id' => $this->warehouse->id,
    ]);
    $order->items()->create(['product_id' => $this->product->id, 'sku' => 'LOC-1', 'name' => 'x', 'quantity' => 3, 'unit_price' => 100, 'tax_rate' => 20, 'tax_amount' => 60, 'line_total' => 360]);
    app(OrderStockService::class)->deductForOrder($order);

    // A-01'den 1, B-01'den 2 düştü. Bu sırada B-01 ana lokasyon olmaktan çıksın (otomatik giriş başka yere giderdi).
    $c1 = $this->warehouse->locations()->create(['code' => 'C-01', 'status' => Status::Active, 'sort_order' => 3]);
    $stock->change($this->product, $this->warehouse, 10, StockMovementType::In, null, null, null, true, $c1);

    // 2 adet iade: düşüm sırasıyla önce A-01'e 1, sonra B-01'e 1 döner.
    app(OrderStockService::class)->restockFromOrder($order, $this->product, 2, $this->warehouse, 'İade');

    expect(locationQty($this->product, $a1))->toBe(1)
        ->and(locationQty($this->product, $b1))->toBe(4)
        ->and(locationQty($this->product, $c1))->toBe(10);

    // Siparişte düşülmemiş miktar yedek depoya otomatik lokasyonla girer.
    app(OrderStockService::class)->restockFromOrder($order, $this->product, 5, $this->other, 'İade');
    expect((int) ProductStock::query()->where('product_id', $this->product->id)->where('warehouse_id', $this->other->id)->value('quantity'))->toBe(2);
});

test('locations with stock cannot be deleted and the detail page lists them', function () {
    $a1 = $this->warehouse->locations()->create(['code' => 'A-01', 'status' => Status::Active, 'sort_order' => 1]);
    app(StockService::class)->change($this->product, $this->warehouse, 2, StockMovementType::In, null, null, null, true, $a1);

    $this->actingAs($this->admin)->delete(route('panel.shop.warehouse-locations.destroy', $a1->id));
    expect(WarehouseLocation::query()->whereKey($a1->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->delete(route('panel.shop.warehouse-locations.destroy', $this->warehouse->unassignedLocation()->id));
    expect($this->warehouse->locations()->where('is_unassigned', true)->exists())->toBeTrue();

    $this->actingAs($this->admin)->get(route('panel.shop.warehouses.show', [$this->warehouse->id, 'lokasyon' => $a1->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('panel/shop/warehouses/show')
            ->has('locations', 2)
            ->where('locations.0.code', 'A-01')
            ->where('locations.0.quantity', 2)
            ->has('stocks.data', 1)
            ->where('stocks.data.0.locations.0.code', 'A-01'));

    $this->actingAs($this->admin)->get(route('panel.shop.warehouses.export', $this->warehouse->id))->assertOk();
});
