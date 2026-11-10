<?php

declare(strict_types=1);

use App\Enums\Shop\StockCountStatus;
use App\Enums\Shop\StockMovementType;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\Currency;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockCountService;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function countProduct(string $sku, StockTracking $tracking = StockTracking::None): Product
{
    return Product::query()->create([
        'sku' => $sku, 'barcode' => "869{$sku}", 'name' => "Ürün {$sku}", 'slug' => strtolower($sku), 'price' => 100,
        'currency_id' => Currency::query()->value('id'), 'tax_rate' => 20, 'sale_mode' => 'cart',
        'unit' => 'Adet', 'status' => Status::Active, 'tracking' => $tracking,
    ])->fresh();
}

function whStock(Product $product, Warehouse $warehouse): int
{
    return (int) LocationStock::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->sum('quantity');
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
    $this->warehouse = Warehouse::query()->create(['code' => 'SYD', 'name' => 'Sayım Depo', 'is_default' => false, 'is_sellable' => true, 'status' => Status::Active]);
    $this->shelfA = $this->warehouse->locations()->create(['code' => 'A-01', 'status' => Status::Active]);
    $this->shelfB = $this->warehouse->locations()->create(['code' => 'B-01', 'status' => Status::Active]);
    $this->stock = app(StockService::class);
    $this->counts = app(StockCountService::class);
});

test('scanned quantities accumulate and approval writes the difference as count adjustments', function () {
    $product = countProduct('SY1');
    $this->stock->change($product, $this->warehouse, 10, StockMovementType::In, location: $this->shelfA);

    $count = $this->counts->open($this->warehouse, null, null, $this->admin);
    $this->counts->record($count, $product, $this->shelfA, null, null, 5, true, $this->admin);
    $line = $this->counts->record($count, $product, $this->shelfA, null, null, 2, true, $this->admin);

    expect($line->counted)->toBe(7)->and($line->expected)->toBe(10)->and($count->lines()->count())->toBe(1);

    $this->counts->approve($count, false, $this->admin);

    expect(whStock($product, $this->warehouse))->toBe(7)
        ->and($count->fresh()->status)->toBe(StockCountStatus::Completed);

    $movement = StockMovement::query()->where('reference_id', $count->id)->sole();
    expect($movement->type)->toBe(StockMovementType::Adjust)->and($movement->quantity)->toBe(-3);
});

test('sales made after counting are not undone on approval', function () {
    $product = countProduct('SY2');
    $this->stock->change($product, $this->warehouse, 10, StockMovementType::In, location: $this->shelfA);

    $count = $this->counts->open($this->warehouse, null, null, $this->admin);
    $this->counts->record($count, $product, $this->shelfA, null, null, 8, false, $this->admin);

    // Sayımdan sonra 3 adet satıldı.
    $this->stock->change($product, $this->warehouse, -3, StockMovementType::Sale, location: $this->shelfA);

    $this->counts->approve($count, false, $this->admin);

    expect(whStock($product, $this->warehouse))->toBe(5);
});

test('uncounted stock is zeroed only when requested', function () {
    $counted = countProduct('SY3');
    $missed = countProduct('SY4');
    $this->stock->change($counted, $this->warehouse, 4, StockMovementType::In, location: $this->shelfA);
    $this->stock->change($missed, $this->warehouse, 6, StockMovementType::In, location: $this->shelfB);

    $partial = $this->counts->open($this->warehouse, null, null, $this->admin);
    $this->counts->record($partial, $counted, $this->shelfA, null, null, 4, false, $this->admin);
    expect($this->counts->uncounted($partial))->toHaveCount(1);
    $this->counts->approve($partial, false, $this->admin);
    expect(whStock($missed, $this->warehouse))->toBe(6);

    $full = $this->counts->open($this->warehouse, null, null, $this->admin);
    $this->counts->record($full, $counted, $this->shelfA, null, null, 4, false, $this->admin);
    $this->counts->approve($full, true, $this->admin);

    expect(whStock($missed, $this->warehouse))->toBe(0)
        ->and(whStock($counted, $this->warehouse))->toBe(4);
});

test('a location scoped count only zeroes its own location', function () {
    $product = countProduct('SY5');
    $this->stock->change($product, $this->warehouse, 3, StockMovementType::In, location: $this->shelfA);
    $this->stock->change($product, $this->warehouse, 5, StockMovementType::In, location: $this->shelfB);

    $count = $this->counts->open($this->warehouse, $this->shelfA, null, $this->admin);

    expect(fn () => $this->counts->record($count, $product, $this->shelfB, null, null, 1, true, $this->admin))
        ->toThrow(ShopException::class);

    $this->counts->approve($count, true, $this->admin);

    expect(whStock($product, $this->warehouse))->toBe(5);
});

test('overlapping open counts are refused', function () {
    $this->counts->open($this->warehouse, $this->shelfA, null, $this->admin);
    $this->counts->open($this->warehouse, $this->shelfB, null, $this->admin);

    expect(fn () => $this->counts->open($this->warehouse, $this->shelfA, null, $this->admin))->toThrow(ShopException::class)
        ->and(fn () => $this->counts->open($this->warehouse, null, null, $this->admin))->toThrow(ShopException::class);
});

test('lot tracked products are counted per lot', function () {
    ShopSettings::save(['traceability_enabled' => true]);
    $product = countProduct('SY6', StockTracking::Lot);
    $lot = StockLot::resolve($product, 'L1');
    $this->stock->change($product, $this->warehouse, 10, StockMovementType::In, location: $this->shelfA, lot: $lot);

    $count = $this->counts->open($this->warehouse, null, null, $this->admin);

    expect(fn () => $this->counts->record($count, $product, $this->shelfA, null, null, 1, true, $this->admin))
        ->toThrow(ShopException::class);

    $this->counts->record($count, $product, $this->shelfA, 'l1', null, 9, false, $this->admin);
    $this->counts->record($count, $product, $this->shelfA, 'L2', null, 2, false, $this->admin);
    $this->counts->approve($count, false, $this->admin);

    $l2 = StockLot::query()->where('product_id', $product->id)->where('code', 'L2')->sole();

    expect((int) LocationStock::query()->where('stock_lot_id', $lot->id)->sum('quantity'))->toBe(9)
        ->and((int) LocationStock::query()->where('stock_lot_id', $l2->id)->sum('quantity'))->toBe(2);
});

test('a closed count cannot be changed and cancelling writes no movement', function () {
    $product = countProduct('SY7');
    $this->stock->change($product, $this->warehouse, 2, StockMovementType::In, location: $this->shelfA);

    $count = $this->counts->open($this->warehouse, null, null, $this->admin);
    $this->counts->record($count, $product, $this->shelfA, null, null, 9, false, $this->admin);
    $this->counts->cancel($count, $this->admin);

    expect(fn () => $this->counts->record($count, $product, $this->shelfA, null, null, 1, true, $this->admin))->toThrow(ShopException::class)
        ->and(fn () => $this->counts->approve($count, false, $this->admin))->toThrow(ShopException::class)
        ->and(whStock($product, $this->warehouse))->toBe(2)
        ->and($count->movements()->count())->toBe(0);
});

test('panel screens: start, barcode lookup, scan and approve', function () {
    $product = countProduct('SY8');
    $this->stock->change($product, $this->warehouse, 1, StockMovementType::In, location: $this->shelfA);

    $this->actingAs($this->admin)->get(route('panel.shop.stock-counts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/stock-counts/index'));

    $this->post(route('panel.shop.stock-counts.store'), ['warehouse_id' => $this->warehouse->id])->assertRedirect();
    $count = StockCount::query()->sole();

    $this->getJson(route('panel.shop.stock-counts.lookup', ['stockCount' => $count->id, 'q' => '869SY8']))
        ->assertOk()
        ->assertJsonPath('exact', true)
        ->assertJsonPath('data.0.id', $product->id);

    $this->post(route('panel.shop.stock-counts.lines.store', $count->id), [
        'product_id' => $product->id, 'location_id' => $this->shelfA->id, 'quantity' => 3, 'mode' => 'add',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $line = $count->lines()->sole();
    $this->put(route('panel.shop.stock-counts.lines.update', ['stockCount' => $count->id, 'line' => $line->id]), ['counted' => 4])->assertRedirect();

    $this->get(route('panel.shop.stock-counts.show', $count->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/shop/stock-counts/show')
            ->where('summary.counted', 4)
            ->where('summary.surplus', 3));

    $this->post(route('panel.shop.stock-counts.approve', $count->id))->assertRedirect();

    expect(whStock($product, $this->warehouse))->toBe(4);
});
