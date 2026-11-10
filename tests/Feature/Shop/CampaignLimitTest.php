<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\Campaign;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Support\Shop\Campaigns\CampaignUsages;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->try = Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',', 'exchange_rate' => 1,
        'status' => Status::Active, 'sort_order' => 1,
    ]);
    Setting::query()->updateOrCreate(['key' => 'default_currency_id'], ['value' => $this->try->id, 'group' => 'defaults']);

    $warehouse = Warehouse::query()->updateOrCreate(['code' => 'LMT'], ['name' => 'Limit Depo', 'is_default' => true, 'is_sellable' => true, 'status' => Status::Active]);
    Warehouse::query()->whereKeyNot($warehouse->id)->update(['is_default' => false]);

    $this->product = Product::create([
        'sku' => 'LMT-1', 'name' => 'Limit Ürünü', 'slug' => 'limit-urunu',
        'price' => 100, 'currency_id' => $this->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    ProductStock::create(['product_id' => $this->product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);
    $this->product->syncStockTotal();

    // 2 Al 1 Öde: 2 adette 100 TL kazanç.
    $this->campaign = Campaign::create([
        'name' => '2 Al 1 Öde', 'action' => 'buy_x_pay_y', 'action_settings' => ['buy' => 2, 'pay' => 1],
        'product_scope' => 'all', 'status' => Status::Active,
    ]);
});

function limitCart(Product $product, ?string $email = null): Basket
{
    app()->forgetScopedInstances();
    $cart = Basket::cart();
    $cart->clear();
    $cart->add($product, 2);
    $cart->useCheckout($email, 'bank_transfer', null);
    $cart->totals();

    return $cart;
}

/** Sepetteki kampanyayı siparişe bağlı kullanım olarak yazar. */
function limitUse(Basket $cart, ?User $user = null, string $email = 'a@example.com'): Order
{
    $order = Order::query()->create([
        'number' => 'S'.random_int(100000, 999999), 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer', 'user_id' => $user?->id, 'account_id' => $user?->account_id,
        'customer_name' => 'Ali Veli', 'email' => $email, 'phone' => '0555',
        'billing_address' => ['name' => 'Ali Veli'], 'shipping_address' => ['name' => 'Ali Veli'],
        'currency_code' => 'TRY', 'subtotal' => '100.00', 'tax_total' => '20.00', 'shipping_total' => '0', 'grand_total' => '120.00',
    ]);

    app(CampaignUsages::class)->claim($cart->campaign(), $cart->campaignContext(), $order);

    return $order;
}

it('stops a campaign after its total usage limit and counts cancelled orders back', function () {
    $this->campaign->update(['usage_limit' => 1]);

    $cart = limitCart($this->product);
    expect($cart->campaign())->not->toBeNull();
    $order = limitUse($cart);

    expect(limitCart($this->product)->campaign())->toBeNull();

    // İptal edilen siparişin kullanımı sayılmaz.
    $order->update(['status' => OrderStatus::Cancelled]);
    expect(limitCart($this->product)->campaign())->not->toBeNull();
});

it('limits usage per customer by account, member or e-mail', function () {
    $this->campaign->update(['usage_limit_per_customer' => 1]);
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();

    $this->actingAs($member);
    limitUse(limitCart($this->product), $member, $member->email);
    expect(limitCart($this->product)->campaign())->toBeNull();

    // Başka müşteri (misafir, farklı e-posta) yine yararlanır; aynı e-postalı misafir yararlanamaz.
    auth()->logout();
    expect(limitCart($this->product, 'baska@example.com')->campaign())->not->toBeNull();
    limitUse(limitCart($this->product, 'Misafir@Example.com'), null, 'misafir@example.com');
    expect(limitCart($this->product, 'misafir@example.com')->campaign())->toBeNull();
});

it('stops a campaign when its budget would be exceeded', function () {
    $this->campaign->update(['budget' => '150']);

    limitUse(limitCart($this->product));

    // 100 TL kullanıldı; bir 100 TL daha bütçeyi (150) aşar.
    expect(Campaign::query()->find($this->campaign->id)->usages()->sole()->value)->toEqualDecimal('100')
        ->and(limitCart($this->product)->campaign())->toBeNull();

    $this->campaign->update(['budget' => '200']);
    expect(limitCart($this->product)->campaign())->not->toBeNull();
});

it('rechecks the limit under lock when the order is placed', function () {
    $this->campaign->update(['usage_limit' => 1]);

    // Sepet kampanyayı gördü; bu arada başka bir sipariş son kullanımı aldı.
    $cart = limitCart($this->product);
    limitUse(limitCart($this->product));

    expect(fn () => limitUse($cart))->toThrow(ShopException::class);
});

it('saves limits from the panel and shows the usage', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->put(route('panel.shop.campaigns.update', $this->campaign), [
        'name' => '2 Al 1 Öde', 'product_scope' => 'all', 'action' => 'buy_x_pay_y', 'settings' => ['buy' => 2, 'pay' => 1],
        'shipping_mode' => 'cart', 'usage_limit' => '0', 'budget' => '-5',
    ])->assertSessionHasErrors(['budget']);

    $this->put(route('panel.shop.campaigns.update', $this->campaign), [
        'name' => '2 Al 1 Öde', 'product_scope' => 'all', 'action' => 'buy_x_pay_y', 'settings' => ['buy' => 2, 'pay' => 1],
        'shipping_mode' => 'cart', 'usage_limit' => '100', 'usage_limit_per_customer' => '1', 'budget' => '5000,50',
    ])->assertSessionHasNoErrors();

    $campaign = $this->campaign->fresh();
    expect($campaign->usage_limit)->toBe(100)
        ->and($campaign->usage_limit_per_customer)->toBe(1)
        ->and($campaign->budget)->toEqualDecimal('5000.5');

    limitUse(limitCart($this->product));

    $this->get(route('panel.shop.campaigns.edit', $campaign))
        ->assertInertia(fn ($page) => $page->where('campaign.usage.count', 1)->where('campaign.usage_limit', 100));
});

it('applies payment method, city, weekday and hour conditions', function () {
    $rule = fn (string $field, string $operator, mixed $value) => ['audience_rules' => [[['field' => $field, 'operator' => $operator, 'value' => $value]]]];

    // Havale / EFT ile ödemede: sepette yok, ödeme adımında havale seçilince var.
    $this->campaign->update($rule('payment_method', 'in', ['bank_transfer']));
    app()->forgetScopedInstances();
    $cart = Basket::cart();
    $cart->clear();
    $cart->add($this->product, 2);
    $cart->totals();
    expect($cart->campaign())->toBeNull()
        ->and(limitCart($this->product)->campaign())->not->toBeNull();

    // İl: teslimat ili Bursa ise.
    $this->campaign->update($rule('city', 'in', ['Bursa']));
    $cart = limitCart($this->product);
    expect($cart->campaign())->toBeNull();
    $cart->useCheckout(null, 'bank_transfer', 'BURSA')->totals();
    expect($cart->campaign())->not->toBeNull();

    // Gün: hafta sonu.
    $this->campaign->update($rule('weekday', 'in', ['6', '7']));
    CarbonImmutable::setTestNow('2026-11-07 10:00:00'); // Cumartesi
    expect(limitCart($this->product)->campaign())->not->toBeNull();
    CarbonImmutable::setTestNow('2026-11-09 10:00:00'); // Pazartesi
    expect(limitCart($this->product)->campaign())->toBeNull();

    // Saat: 18:00–21:59.
    $this->campaign->update(['audience_rules' => [[
        ['field' => 'hour', 'operator' => 'gte', 'value' => '18'],
        ['field' => 'hour', 'operator' => 'lte', 'value' => '21'],
    ]]]);
    CarbonImmutable::setTestNow('2026-11-09 19:30:00');
    expect(limitCart($this->product)->campaign())->not->toBeNull();
    CarbonImmutable::setTestNow('2026-11-09 22:05:00');
    expect(limitCart($this->product)->campaign())->toBeNull();
    CarbonImmutable::setTestNow();
});
