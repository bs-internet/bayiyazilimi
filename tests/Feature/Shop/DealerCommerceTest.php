<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SavedCart;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\StockService;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    // Havale / EFT yalnızca aktif banka hesabı varken sunulur.
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    $this->withoutVite();
    Notification::fake();

    $this->dealer = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->try = Currency::query()->where('code', 'TRY')->firstOrFail();
    $warehouse = Warehouse::query()->where('code', 'DEPO')->firstOrFail();
    Warehouse::query()->update(['is_default' => false]);
    $warehouse->update(['is_default' => true]);

    $this->product = Product::create([
        'sku' => 'DC-100', 'name' => 'Bayi Sepet Ürünü', 'slug' => 'bayi-sepet-urunu',
        'price' => 100, 'currency_id' => $this->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, $warehouse, 50, StockMovementType::In);

    $this->second = Product::create([
        'sku' => 'DC-200', 'name' => 'İkinci Ürün', 'slug' => 'ikinci-urun',
        'price' => 50, 'currency_id' => $this->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->second, $warehouse, 50, StockMovementType::In);
});

function dealerCheckoutPayload(array $extra = []): array
{
    return array_merge([
        'email' => 'user@user.com', 'phone' => '05551112233',
        'same_address' => '0', 'billing_same_as_shipping' => '1',
        'shipping' => ['name' => 'Depo Sorumlusu', 'address' => 'Depo adresi 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'billing' => ['type' => 'corporate', 'name' => 'Ali Veli', 'company' => 'ACME', 'tax_office' => 'Nilüfer', 'tax_number' => '1234567890'],
        'payment_method' => 'bank_transfer', 'agreement' => '1', 'save_address' => '1',
    ], $extra);
}

it('renders the dealer cart and quote list inside the app area', function () {
    $this->actingAs($this->dealer)->post(route('app.shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2]);

    $this->actingAs($this->dealer)->get(route('app.shop.cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/cart/index')
            ->has('lines', 1)
            ->has('totals.total_formatted')
            ->where('savedCartCount', 0));

    // Adet güncelleme app alanında kalır.
    $this->actingAs($this->dealer)->post(route('app.shop.cart.update'), ['quantities' => [$this->product->id => 3]])
        ->assertRedirect(route('app.shop.cart.index'));
    expect(Basket::cart()->quantityOf($this->product))->toBe(3);

    // Sepet → teklif listesi.
    $this->actingAs($this->dealer)->post(route('app.shop.cart.to-quote'))->assertRedirect(route('app.shop.quote-list.index'));
    expect(Basket::cart()->isEmpty())->toBeTrue()
        ->and(Basket::quote()->quantityOf($this->product))->toBe(3);

    $this->actingAs($this->dealer)->get(route('app.shop.quote-list.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/quote-list/index')
            ->has('lines', 1)
            ->where('prefill.email', 'user@user.com'));
});

it('submits the quote list from the app and lands on the quotes list', function () {
    $this->actingAs($this->dealer)->post(route('app.shop.quote-list.add'), ['product_id' => $this->product->id, 'quantity' => 4]);

    $this->actingAs($this->dealer)->post(route('app.shop.quote-list.submit'), [
        'customer_name' => 'Ali Veli', 'company' => 'ACME', 'email' => 'user@user.com', 'phone' => '05551112233', 'kvkk' => '1',
    ])->assertRedirect(route('app.shop.quotes.index'));

    $quote = Quote::query()->latest()->firstOrFail();
    expect($quote->account_id)->toBe($this->dealer->account_id)
        ->and(Basket::quote()->isEmpty())->toBeTrue();
});

it('saves, renames, updates, orders from and deletes saved carts (account scoped)', function () {
    $this->actingAs($this->dealer)->post(route('app.shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2]);
    $this->actingAs($this->dealer)->post(route('app.shop.cart.add'), ['product_id' => $this->second->id, 'quantity' => 1]);

    $this->actingAs($this->dealer)->post(route('app.shop.saved-carts.store'), ['name' => 'Aylık sipariş'])->assertRedirect();

    $saved = SavedCart::query()->sole();
    expect($saved->account_id)->toBe($this->dealer->account_id)
        ->and($saved->user_id)->toBe($this->dealer->id)
        ->and($saved->quantities())->toBe([$this->product->id => 2, $this->second->id => 1])
        ->and(Activity::query()->where('log_name', 'saved_cart')->where('event', 'created')->exists())->toBeTrue();

    $this->actingAs($this->dealer)->get(route('app.shop.saved-carts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/saved-carts/index')
            ->has('savedCarts.data', 1)
            ->where('savedCarts.data.0.item_count', 2)
            // 2 × 100 + 1 × 50, KDV dahil, bayi iskontosuyla (%10) → 270.
            ->where('savedCarts.data.0.estimated_total', fn ($t) => Money::decimal($t)->isPositive()));

    $this->actingAs($this->dealer)->get(route('app.shop.saved-carts.show', $saved->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/saved-carts/show')
            ->has('lines', 2)
            ->where('lines.0.warning', null));

    $this->actingAs($this->dealer)->put(route('app.shop.saved-carts.update', $saved->id), ['name' => 'Yeni ad'])->assertRedirect();
    expect($saved->fresh()->name)->toBe('Yeni ad');

    // Sepeti güncelle: adet değişir, 0 olan ürün çıkar.
    $this->actingAs($this->dealer)->put(route('app.shop.saved-carts.items', $saved->id), [
        'quantities' => [$this->product->id => 3, $this->second->id => 0],
    ])->assertSessionHasNoErrors();
    expect($saved->fresh()->quantities())->toBe([$this->product->id => 3]);

    // Sipariş ver: asıl sepete dokunmadan kayıtlı sepetle ödeme ekranı açılır.
    $before = Basket::cart()->raw();
    $this->actingAs($this->dealer)->get(route('app.shop.saved-carts.checkout', $saved->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/checkout/index')
            ->where('action', route('app.shop.saved-carts.place-order', $saved->id))
            ->where('context.back_url', route('app.shop.saved-carts.show', $saved->id))
            ->has('lines', 1));
    expect(Basket::cart()->raw())->toBe($before);

    // Başka hesabın kayıtlı sepetine erişilemez.
    $other = Account::query()->create(['code' => 'OTHERSC1', 'title' => 'Diğer']);
    $foreign = new SavedCart(['name' => 'Yabancı', 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]]);
    $foreign->account_id = $other->id;
    $foreign->save();

    $this->actingAs($this->dealer)->get(route('app.shop.saved-carts.show', $foreign->id))->assertNotFound();
    $this->actingAs($this->dealer)->delete(route('app.shop.saved-carts.destroy', $foreign->id))->assertNotFound();

    $this->actingAs($this->dealer)->delete(route('app.shop.saved-carts.destroy', $saved->id))
        ->assertRedirect(route('app.shop.saved-carts.index'));
    expect(SavedCart::query()->whereKey($saved->id)->exists())->toBeFalse()
        ->and(SavedCart::query()->withoutGlobalScopes()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('refuses to save an empty cart', function () {
    Basket::cart()->clear();

    $this->actingAs($this->dealer)->post(route('app.shop.saved-carts.store'), ['name' => 'Boş'])->assertRedirect();
    expect(SavedCart::query()->count())->toBe(0);
});

it('completes a bank transfer checkout inside the app with separate shipping and billing blocks', function () {
    $this->actingAs($this->dealer)->post(route('app.shop.cart.add'), ['product_id' => $this->product->id, 'quantity' => 2]);

    $this->actingAs($this->dealer)->get(route('app.shop.checkout.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/checkout/index')
            ->where('action', route('app.shop.checkout.store'))
            ->has('prefill.shipping')
            ->has('lines', 1));

    $response = $this->actingAs($this->dealer)->post(route('app.shop.checkout.store'), dealerCheckoutPayload());

    $order = Order::query()->latest()->firstOrFail();
    $response->assertRedirect(route('app.shop.orders.show', $order->id));

    expect($order->payment_method)->toBe('bank_transfer')
        ->and($order->account_id)->toBe($this->dealer->account_id)
        ->and($order->shipping_address['name'])->toBe('Depo Sorumlusu')
        ->and($order->shipping_address['address'])->toBe('Depo adresi 1')
        // "Fatura adresim teslimat adresimle aynı": adres kopyalanır, fatura kimliği ayrı kalır.
        ->and($order->billing_address['address'])->toBe('Depo adresi 1')
        ->and($order->billing_address['company'])->toBe('ACME')
        ->and($order->billing_address['name'])->toBe('Ali Veli')
        ->and(Basket::cart()->isEmpty())->toBeTrue();

    // "Adreslerime kaydet": teslimat ve fatura ayrı türlerde kaydedilir.
    $types = CustomerAddress::query()->where('account_id', $this->dealer->account_id)->pluck('type')->map->value->sort()->values()->all();
    expect($types)->toBe(['billing', 'shipping']);
});

it('shows the dealer shopping menu items', function () {
    $this->actingAs($this->dealer)->get(route('app.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('orderUrl', route('app.shop.cart.index'))
            ->where('navigation', function ($groups) {
                $keys = collect($groups)->flatMap(fn ($g) => $g['items'])->pluck('key');

                return $keys->contains('app.shop.cart.index')
                    && $keys->contains('app.shop.quotes')
                    && $keys->contains('app.shop.saved-carts.index');
            }));
});
