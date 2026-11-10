<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\OrderStockService;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function pickProduct(string $sku, bool $trackStock = true): Product
{
    return Product::query()->create([
        'sku' => $sku, 'name' => "Ürün {$sku}", 'slug' => strtolower($sku), 'price' => 100,
        'currency_id' => Currency::query()->value('id'), 'tax_rate' => 20, 'sale_mode' => 'cart',
        'unit' => 'Adet', 'status' => Status::Active, 'track_stock' => $trackStock,
    ])->fresh();
}

/**
 * @param  array<int, array{0: Product, 1: int}>  $lines
 */
function pickOrder(string $number, array $lines, Warehouse $warehouse, OrderStatus $status = OrderStatus::Processing): Order
{
    $order = Order::query()->create([
        'number' => $number, 'status' => $status, 'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali', 'email' => 'a@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali', 'city' => 'İzmir'], 'currency_code' => 'TRY',
        'subtotal' => 100, 'tax_total' => 20, 'grand_total' => 120, 'warehouse_id' => $warehouse->id,
    ]);

    foreach ($lines as [$product, $qty]) {
        $order->items()->create(['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'quantity' => $qty, 'unit_price' => 100, 'tax_rate' => 20, 'tax_amount' => 20, 'line_total' => 120]);
    }

    app(OrderStockService::class)->deductForOrder($order);

    return $order->fresh();
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
    Warehouse::query()->update(['is_default' => false]);
    $this->warehouse = Warehouse::query()->create(['code' => 'TPL', 'name' => 'Toplama Depo', 'is_default' => true, 'is_sellable' => true, 'status' => Status::Active]);
    $this->shelfB = $this->warehouse->locations()->create(['code' => 'B-01', 'sort_order' => 2, 'status' => Status::Active]);
    $this->shelfA = $this->warehouse->locations()->create(['code' => 'A-01', 'sort_order' => 1, 'status' => Status::Active]);
    $this->stock = app(StockService::class);
});

test('pick list merges orders per location in picking order', function () {
    $bolt = pickProduct('PL-BOLT');
    $nut = pickProduct('PL-NUT');
    $service = pickProduct('PL-SRV', trackStock: false);
    $this->stock->change($bolt, $this->warehouse, 10, StockMovementType::In, location: $this->shelfB);
    $this->stock->change($nut, $this->warehouse, 10, StockMovementType::In, location: $this->shelfA);

    $first = pickOrder('S-PL-1', [[$bolt, 2], [$nut, 1]], $this->warehouse);
    $second = pickOrder('S-PL-2', [[$bolt, 3], [$service, 1]], $this->warehouse);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.orders.pick-list', ['orders' => [$first->id, $second->id]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/shop/orders/pick-list')
            ->where('selected', true)
            ->has('orders', 2)
            ->has('list.warehouses', 1)
            ->where('list.warehouses.0.lines.0.location', 'A-01')
            ->where('list.warehouses.0.lines.0.sku', 'PL-NUT')
            ->where('list.warehouses.0.lines.1.location', 'B-01')
            ->where('list.warehouses.0.lines.1.quantity', 5)
            ->where('list.warehouses.0.lines.1.orders', [['number' => 'S-PL-1', 'quantity' => 2], ['number' => 'S-PL-2', 'quantity' => 3]])
            ->where('list.unlocated.0.sku', 'PL-SRV')
            ->where('list.totals.quantity', 7));
});

test('without a selection the pick list covers processing orders and flags shortages', function () {
    $product = pickProduct('PL-ONE');
    $this->stock->change($product, $this->warehouse, 1, StockMovementType::In, location: $this->shelfA);

    pickOrder('S-PL-3', [[$product, 1]], $this->warehouse);
    pickOrder('S-PL-4', [[$product, 2]], $this->warehouse);
    pickOrder('S-PL-5', [[$product, 1]], $this->warehouse, OrderStatus::Shipped);

    $this->actingAs($this->admin)
        ->get(route('panel.shop.orders.pick-list'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected', false)
            ->has('orders', 2)
            ->where('list.warehouses.0.lines.0.location', 'A-01')
            ->where('list.warehouses.0.lines.0.short', false)
            ->where('list.warehouses.0.lines.1.unassigned', true)
            ->where('list.warehouses.0.lines.1.short', true));
});
