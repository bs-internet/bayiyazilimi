<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    $this->withoutVite();
    Notification::fake();

    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->admin = User::factory()->admin()->create();
    $this->main = Warehouse::query()->where('code', 'DEPO')->firstOrFail();
    $this->region = Warehouse::query()->create(['code' => 'IZM', 'name' => 'İzmir Bölge', 'is_default' => false, 'is_sellable' => true, 'status' => Status::Active, 'sort_order' => 5]);

    $this->product = Product::query()->create([
        'sku' => 'BLG-1', 'name' => 'Bölge Ürünü', 'slug' => 'bolge-urunu', 'price' => 100,
        'currency_id' => Currency::query()->where('code', 'TRY')->value('id'), 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    $stock = app(StockService::class);
    $stock->change($this->product, $this->main, 10, StockMovementType::In);
    $stock->change($this->product, $this->region, 2, StockMovementType::In);
});

function regionQty(Product $product, Warehouse $warehouse): int
{
    return (int) ProductStock::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity');
}

test('admin assigns a region warehouse in dealer settings and it is cleared for customers', function () {
    $this->actingAs($this->admin)->put(route('panel.shop.customers.type', $this->member), [
        'type' => CustomerType::Dealer->value, 'warehouse_id' => $this->region->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->member->fresh()->account->warehouse_id)->toBe($this->region->id);

    $this->get(route('panel.members.show', $this->member))
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.data.warehouse.id', $this->region->id)
            ->where('warehouses', fn ($options) => collect($options)->contains('value', $this->region->id)));

    // "Müşteri" türünü vitrin modülü açar; modülsüz çekirdekte de aynı akışı sınamak için.
    hooks()->filter('shop.customer_types', fn (array $value) => [...$value, CustomerType::Customer]);

    $this->put(route('panel.shop.customers.type', $this->member), ['type' => CustomerType::Customer->value])->assertRedirect();

    expect($this->member->fresh()->account->warehouse_id)->toBeNull();
});

test('a dealer order is taken from the region warehouse first and the rest from others', function () {
    Account::query()->whereKey($this->member->account_id)->update(['type' => CustomerType::Dealer->value, 'warehouse_id' => $this->region->id]);

    $this->actingAs($this->member)->post(route('app.shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 5]);
    $this->post(route('app.shop.checkout.store'), [
        'email' => 'user@user.com', 'phone' => '05551112233',
        'billing' => ['type' => 'individual', 'name' => 'Ali Veli', 'address' => 'Adres 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => '1', 'payment_method' => 'bank_transfer', 'agreement' => '1',
    ])->assertRedirect();

    $order = Order::query()->latest()->firstOrFail();

    expect($order->warehouse_id)->toBe($this->region->id)
        ->and(regionQty($this->product, $this->region))->toBe(0)
        ->and(regionQty($this->product, $this->main))->toBe(7);
});

test('quick order shows the region stock next to the total for dealers with a region', function () {
    $this->actingAs($this->member)->getJson(route('app.shop.products.search', ['q' => 'BLG-1']))
        ->assertOk()
        ->assertJsonPath('0.stock', 12)
        ->assertJsonPath('0.region_stock', null);

    Account::query()->whereKey($this->member->account_id)->update(['type' => CustomerType::Dealer->value, 'warehouse_id' => $this->region->id]);

    $this->getJson(route('app.shop.products.search', ['q' => 'BLG-1']))
        ->assertJsonPath('0.stock', 12)
        ->assertJsonPath('0.region_stock', 2);

    // Pasif / satılamaz depo bölge sayılmaz.
    $this->region->update(['is_sellable' => false]);

    $this->getJson(route('app.shop.products.search', ['q' => 'BLG-1']))->assertJsonPath('0.region_stock', null);
});
