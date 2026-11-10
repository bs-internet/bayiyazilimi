<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Currency;
use App\Models\DealerGroup;
use App\Models\Product;
use App\Models\User;
use App\Services\Shop\Basket;
use App\Services\Shop\PriceCalculator;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    $this->admin = User::factory()->admin()->create();
    $this->dealer = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->customer = sampleCustomer();
    $this->try = Currency::query()->where('code', 'TRY')->firstOrFail();
});

function dealerTestProduct(Currency $currency, array $attributes = []): Product
{
    // forceFill: "audience" vitrin modülünün kolonu (çekirdeğin fillable listesinde yok).
    $product = new Product;
    $product->forceFill([
        'sku' => 'BY-'.random_int(1000, 9999), 'name' => 'Bayi Ürünü', 'slug' => 'bayi-urunu-'.random_int(1000, 9999),
        'price' => 100, 'currency_id' => $currency->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
        ...$attributes,
    ])->save();

    return $product;
}

/**
 * PriceCalculator istek kapsamlıdır (scoped); kullanıcı değişince yenisi alınır.
 */
function freshPrices(): PriceCalculator
{
    app()->forgetScopedInstances();

    return app(PriceCalculator::class);
}

it('seeds the demo dealer and customer', function () {
    expect($this->dealer->isDealer())->toBeTrue()
        ->and($this->customer->isDealer())->toBeFalse()
        ->and($this->dealer->account->dealerGroup->name)->toBe('Standart Bayi');
});

it('creates panel members as dealers with group and special discount', function () {
    $group = DealerGroup::create(['name' => 'Altın', 'discount_rate' => 15]);

    $this->actingAs($this->admin)->post(route('panel.members.store'), [
        'name' => 'Bayi Firma', 'email' => 'bayi@example.com', 'status' => UserStatus::Active->value,
        'company' => 'Bayi Ltd.', 'dealer_group_id' => $group->id, 'discount_rate' => '22,5',
    ])->assertSessionHasErrors('discount_rate');

    $this->actingAs($this->admin)->post(route('panel.members.store'), [
        'name' => 'Bayi Firma', 'email' => 'bayi@example.com', 'status' => UserStatus::Active->value,
        'company' => 'Bayi Ltd.', 'dealer_group_id' => $group->id, 'discount_rate' => '22.5',
    ])->assertRedirect();

    $user = User::query()->where('email', 'bayi@example.com')->with('account.dealerGroup')->firstOrFail();

    expect($user->user_type)->toBe(UserType::Member)
        ->and($user->isDealer())->toBeTrue()
        ->and($user->account->title)->toBe('Bayi Ltd.')
        ->and($user->account->dealer_group_id)->toBe($group->id)
        ->and($user->account->dealerDiscountRate())->toEqualDecimal('22.5');
});

it('manages dealer groups and guards groups that still have dealers', function () {
    $this->actingAs($this->admin)->get(route('panel.shop.dealer-groups.index'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/members/dealer-groups')->has('groups', 1));

    $this->actingAs($this->admin)->post(route('panel.shop.dealer-groups.store'), ['name' => 'Gümüş', 'discount_rate' => '7,5'])
        ->assertSessionHasNoErrors();

    $silver = DealerGroup::query()->where('name', 'Gümüş')->firstOrFail();
    expect($silver->discount_rate)->toEqualDecimal('7.5');

    $this->actingAs($this->admin)->put(route('panel.shop.dealer-groups.update', $silver), ['name' => 'Gümüş', 'discount_rate' => 8])
        ->assertSessionHasNoErrors();
    expect($silver->fresh()->discount_rate)->toEqualDecimal('8.0');

    $standard = DealerGroup::query()->where('name', 'Standart Bayi')->firstOrFail();
    $this->actingAs($this->admin)->delete(route('panel.shop.dealer-groups.destroy', $standard));
    expect(DealerGroup::query()->whereKey($standard->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->delete(route('panel.shop.dealer-groups.destroy', $silver));
    expect(DealerGroup::query()->whereKey($silver->id)->exists())->toBeFalse();
});

it('applies dealer discounts: special rate over group, none on product discounts', function () {
    $plain = dealerTestProduct($this->try);
    $onSale = dealerTestProduct($this->try, ['price' => 80, 'compare_price' => 100]);

    // Misafir: bayi indirimi yok.
    $prices = freshPrices();
    expect($prices->net($plain, $this->try))->toEqualDecimal('100.0')
        ->and($prices->net($onSale, $this->try))->toEqualDecimal('80.0');

    // Bayi (grup %10).
    $this->actingAs($this->dealer);
    $prices = freshPrices();
    expect($prices->net($plain, $this->try))->toEqualDecimal('90.0')
        ->and($prices->compareNet($plain, $this->try))->toEqualDecimal('100.0')
        ->and($prices->discount($plain)['source'])->toBe('dealer')
        ->and($prices->net($onSale, $this->try))->toEqualDecimal('80.0');

    // Bayiye özel oran grubun yerine geçer.
    $this->dealer->account->update(['discount_rate' => 30]);
    $this->dealer->unsetRelation('account');
    $prices = freshPrices();
    expect($prices->net($plain, $this->try))->toEqualDecimal('70.0');

    // Müşteriye bayi indirimi uygulanmaz.
    $this->actingAs($this->customer);
    expect(freshPrices()->net($plain, $this->try))->toEqualDecimal('100.0');
});

it('charges the dealer price in the cart and shows prices by the display setting', function () {
    $product = dealerTestProduct($this->try);

    $this->actingAs($this->dealer);
    freshPrices();

    $cart = Basket::cart();
    $cart->add($product, 2);
    $lines = $cart->lines($this->try);
    expect($lines->first()['unit_net'] ?? null)->toEqualDecimal('90.0');

    // Bayi varsayılanı KDV hariç, ziyaretçi varsayılanı KDV dahil.
    expect(freshPrices()->displaysGross())->toBeFalse()
        ->and(freshPrices()->display($product, $this->try))->toEqualDecimal('90.0');

    ShopSettings::save(['dealer_price_display' => 'gross']);
    expect(freshPrices()->display($product, $this->try))->toEqualDecimal('108.0');

    auth()->logout();
    expect(freshPrices()->displaysGross())->toBeTrue()
        ->and(freshPrices()->display($product, $this->try))->toEqualDecimal('120.0');
});
