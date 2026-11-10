<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Enums\UserType;
use App\Models\BankAccount;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Panel\Shop\CampaignService;
use App\Services\Shop\Basket;
use App\Services\Shop\ShopSettings;
use App\Support\Shop\Campaigns\CampaignEngine;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Havale / EFT yalnızca aktif banka hesabı varken sunulur.
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    $this->withoutVite();
    Notification::fake();

    $this->try = Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',', 'exchange_rate' => 1,
        'status' => Status::Active, 'sort_order' => 1,
    ]);
    Setting::query()->updateOrCreate(['key' => 'default_currency_id'], ['value' => $this->try->id, 'group' => 'defaults']);

    $this->warehouse = Warehouse::query()->updateOrCreate(['code' => 'KMP'], ['name' => 'Kampanya Depo', 'is_default' => true, 'is_sellable' => true, 'status' => Status::Active]);
    Warehouse::query()->update(['is_default' => false]);
    $this->warehouse->update(['is_default' => true]);
    ShopSettings::save(['bank_transfer_enabled' => '1', 'shipping_fee' => '50']);

    $this->brand = Brand::query()->updateOrCreate(['slug' => 'kmp-marka'], ['name' => 'KMP Marka', 'status' => Status::Active]);
    $this->parent = Category::query()->updateOrCreate(['slug' => 'kmp-ana'], ['name' => 'KMP Ana', 'status' => Status::Active]);
    $this->child = Category::query()->updateOrCreate(['slug' => 'kmp-alt'], ['name' => 'KMP Alt', 'parent_id' => $this->parent->id, 'status' => Status::Active]);
    $this->other = Category::query()->updateOrCreate(['slug' => 'kmp-diger'], ['name' => 'KMP Diğer', 'status' => Status::Active]);

    $this->product = kmpProduct($this, ['category_id' => $this->child->id, 'brand_id' => $this->brand->id]);
});

function kmpProduct(object $test, array $attributes = []): Product
{
    $suffix = random_int(10000, 99999);
    $product = Product::create([
        'sku' => "KMP-{$suffix}", 'name' => "Kampanya Ürünü {$suffix}", 'slug' => "kampanya-urunu-{$suffix}",
        'price' => 100, 'currency_id' => $test->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
        ...$attributes,
    ]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $test->warehouse->id, 'quantity' => 50]);
    $product->syncStockTotal();

    return $product;
}

function kmpCampaign(array $attributes = []): Campaign
{
    return Campaign::create([
        'name' => '2 Al 1 Öde', 'action' => 'buy_x_pay_y', 'action_settings' => ['buy' => 2, 'pay' => 1],
        'product_scope' => 'all', 'status' => Status::Active,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, int>  $items  ürün id => adet
 */
function kmpCart(array $items): Basket
{
    app()->forgetScopedInstances();
    $cart = Basket::cart();
    $cart->clear();

    foreach ($items as $id => $qty) {
        $cart->add(Product::query()->findOrFail($id), $qty);
    }

    return $cart;
}

it('applies X al Y öde per line', function () {
    kmpCampaign();

    $line = kmpCart([$this->product->id => 5])->lines()->first();

    expect($line['discount'])->toEqualDecimal('200.0')
        ->and($line['campaign'])->toBe('2 Al 1 Öde')
        ->and($line['line_net'])->toEqualDecimal('300.0');
});

it('applies percent and amount discounts only to matching products', function () {
    $other = kmpProduct($this, ['category_id' => $this->other->id]);
    kmpCampaign([
        'name' => 'Ana kategoride %10', 'action' => 'percent', 'action_settings' => ['rate' => 10],
        'product_scope' => 'match', 'product_rules' => [['field' => 'category', 'operator' => 'in', 'value' => [$this->parent->id]]],
    ]);

    $lines = kmpCart([$this->product->id => 2, $other->id => 1])->lines()->keyBy(fn ($l) => $l['product']->id);

    expect($lines[$this->product->id]['discount'])->toEqualDecimal('20.0')
        ->and($lines[$other->id]['discount'])->toEqualDecimal('0.0')
        ->and($lines[$other->id]['campaign'])->toBeNull();

    Campaign::query()->delete();
    kmpCampaign(['name' => 'Sepete 30 TL', 'action' => 'amount', 'action_settings' => ['amount' => 30, 'mode' => 'cart']]);

    $lines = kmpCart([$this->product->id => 2, $other->id => 1])->lines()->keyBy(fn ($l) => $l['product']->id);

    // 30 TL, 200 ve 100 TL'lik satırlara orantılı: 20 + 10.
    expect($lines[$this->product->id]['discount'])->toEqualDecimal('20.0')
        ->and($lines[$other->id]['discount'])->toEqualDecimal('10.0');
});

it('reads action amounts in the selected currency and converts them to the cart', function () {
    Currency::query()->updateOrCreate(['code' => 'EUR'], [
        'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',', 'exchange_rate' => 40,
        'status' => Status::Active, 'sort_order' => 2,
    ]);
    kmpCampaign(['name' => '1 € adet başına', 'action' => 'amount', 'action_settings' => ['amount' => 1, 'mode' => 'unit'], 'currency_code' => 'EUR']);

    // 1 € = 40 TL, 2 adet → 80 TL indirim; özet seçilen para biriminde.
    $totals = kmpCart([$this->product->id => 2])->totals();

    expect($totals['discount'])->toEqualDecimal('80.0')
        ->and($totals['campaign']['summary'])->toContain('€');
});

it('applies only one campaign per cart: the most beneficial one', function () {
    kmpCampaign(['name' => '%10', 'action' => 'percent', 'action_settings' => ['rate' => 10]]);
    kmpCampaign(['name' => '%60', 'action' => 'percent', 'action_settings' => ['rate' => 60]]);
    kmpCampaign(['name' => 'Ücretsiz kargo', 'action' => 'shipping', 'action_settings' => [], 'shipping_mode' => 'free']);

    // 3 × 100 TL: %60 = 180 TL > ücretsiz kargo (150 TL) > %10. Birikmez: kargo ödenir.
    Campaign::query()->where('name', 'Ücretsiz kargo')->update(['status' => Status::Passive]);
    $paid = kmpCart([$this->product->id => 3])->totals()['shipping'];
    Campaign::query()->where('name', 'Ücretsiz kargo')->update(['status' => Status::Active]);

    $totals = kmpCart([$this->product->id => 3])->totals();
    expect($totals['campaign']['name'])->toBe($paid->isLessThan(180) ? '%60' : 'Ücretsiz kargo')
        ->and($totals['campaign']['others'])->toBe(2);

    if ($paid->isLessThan(180)) {
        expect($totals['discount'])->toEqualDecimal('180.0')->and($totals['shipping'])->toEqualDecimal($paid);
    }
});

it('runs a shipping-only campaign: free or fixed fee', function () {
    $normal = kmpCart([$this->product->id => 1])->totals()['shipping'];
    $campaign = kmpCampaign(['name' => 'Ücretsiz kargo', 'action' => 'shipping', 'action_settings' => [], 'shipping_mode' => 'free']);

    $totals = kmpCart([$this->product->id => 1])->totals();

    expect($normal->isGreaterThan(20))->toBeTrue()
        ->and($totals['campaign']['name'])->toBe('Ücretsiz kargo')
        ->and($totals['campaign']['shipping'])->toBe('Ücretsiz kargo')
        ->and($totals['shipping'])->toEqualDecimal('0.0')
        ->and($totals['discount'])->toEqualDecimal('0.0');

    $campaign->update(['shipping_mode' => 'fixed', 'shipping_amount' => 20]);
    $totals = kmpCart([$this->product->id => 1])->totals();

    expect($totals['shipping'])->toEqualDecimal('20.0')
        ->and($totals['campaign']['summary'])->toBe('Kargo 20,00 ₺');
});

it('combines the action with the campaign shipping: 2 Al 1 Öde + ücretsiz kargo', function () {
    kmpCampaign(['shipping_mode' => 'free']);

    $totals = kmpCart([$this->product->id => 2])->totals();

    expect($totals['discount'])->toEqualDecimal('100.0')
        ->and($totals['shipping'])->toEqualDecimal('0.0')
        ->and($totals['campaign']['summary'])->toBe('2 Al 1 Öde + ücretsiz kargo');

    // Yalnız kargo kazancı da kampanyayı uygulanabilir kılar (indirim yokken).
    expect(kmpCart([$this->product->id => 1])->totals()['shipping'])->toEqualDecimal('0.0');
});

it('adds the gift product as a free line', function () {
    $gift = kmpProduct($this, ['name' => 'Hediye Mop']);
    kmpCampaign(['name' => 'Hediyeli', 'action' => 'gift', 'action_settings' => ['product_id' => $gift->id, 'quantity' => 1]]);

    $lines = kmpCart([$this->product->id => 1])->lines();
    $giftLine = $lines->firstWhere('gift', true);

    expect($lines)->toHaveCount(2)
        ->and($giftLine['product']->id)->toBe($gift->id)
        ->and($giftLine['line_gross'])->toEqualDecimal('0.0')
        ->and($giftLine['campaign'])->toBe('Hediyeli');
});

it('respects dates and status and never applies to the quote list', function () {
    $campaign = kmpCampaign(['starts_at' => now()->addDay()]);
    expect(kmpCart([$this->product->id => 2])->lines()->first()['discount'])->toEqualDecimal('0.0');

    $campaign->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]);
    expect(kmpCart([$this->product->id => 2])->lines()->first()['discount'])->toEqualDecimal('0.0');

    $campaign->update(['ends_at' => null, 'status' => Status::Passive]);
    expect(kmpCart([$this->product->id => 2])->lines()->first()['discount'])->toEqualDecimal('0.0');

    $campaign->update(['status' => Status::Active]);
    expect(kmpCart([$this->product->id => 2])->lines()->first()['discount'])->toEqualDecimal('100.0');

    app()->forgetScopedInstances();
    $quote = Basket::quote();
    $quote->add($this->product, 2);
    expect($quote->lines()->first()['discount'])->toEqualDecimal('0.0');
});

it('shows badges on covered products', function () {
    $other = kmpProduct($this, ['category_id' => $this->other->id]);
    kmpCampaign(['product_scope' => 'match', 'product_rules' => [['field' => 'brand', 'operator' => 'in', 'value' => [$this->brand->id]]]]);
    kmpCampaign(['name' => 'Kargo', 'action' => 'shipping', 'action_settings' => [], 'shipping_mode' => 'free', 'badge' => 'Bedava Kargo']);

    app()->forgetScopedInstances();
    $engine = app(CampaignEngine::class);

    expect($engine->badgesFor($this->product, null))->toBe(['2 Al 1 Öde', 'Bedava Kargo'])
        ->and($engine->badgesFor($other, null))->toBe(['Bedava Kargo']);
});

it('blocks panel users without the campaign permission', function () {
    $editor = User::factory()->create(['user_type' => UserType::Admin]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($editor)->get(route('panel.shop.campaigns.index'))->assertForbidden();
});

it('caps a percent discount and spreads the cap over the lines', function () {
    $other = kmpProduct($this, ['price' => 300]);
    kmpCampaign(['name' => '%50, en fazla 100 TL', 'action' => 'percent', 'action_settings' => ['rate' => 50, 'max_amount' => '100']]);

    $cart = kmpCart([$this->product->id => 1, $other->id => 1]);
    $lines = $cart->lines()->keyBy(fn ($l) => $l['product']->id);

    // %50 → 50 + 150 = 200 TL; sınır 100 TL, satırlara 1:3 oranında.
    expect($cart->totals()['discount'])->toEqualDecimal('100')
        ->and($lines[$this->product->id]['discount'])->toEqualDecimal('25')
        ->and($lines[$other->id]['discount'])->toEqualDecimal('75')
        ->and($cart->totals()['campaign']['summary'])->toContain('en fazla');

    // Sınır aşılmıyorsa yüzde aynen uygulanır.
    expect(kmpCart([$this->product->id => 1])->totals()['discount'])->toEqualDecimal('50');
});

it('selects the products a live campaign covers for the storefront listing', function () {
    $other = kmpProduct($this, ['category_id' => $this->other->id]);
    $covered = function () {
        app()->forgetScopedInstances();
        $query = Product::query();
        app(CampaignEngine::class)->constrainCovered($query, null);

        return $query->pluck('id')->all();
    };

    expect($covered())->toBe([]);

    // Ana kategori kampanyası alt kategorideki ürünü de kapsar.
    $campaign = kmpCampaign(['product_scope' => 'match', 'product_rules' => [['field' => 'category', 'operator' => 'in', 'value' => [$this->parent->id]]]]);
    expect($covered())->toBe([$this->product->id]);

    // "Hiçbiri değil": diğer ürün.
    $campaign->update(['product_rules' => [['field' => 'brand', 'operator' => 'not_in', 'value' => [$this->brand->id]]]]);
    expect($covered())->toContain($other->id)->not->toContain($this->product->id);

    // Sorguya çevrilemeyen koşul (bilinmeyen) içeren kampanya listeye katılmaz.
    $campaign->update(['product_rules' => [['field' => 'unknown', 'operator' => 'in', 'value' => ['x']]]]);
    expect($covered())->toBe([]);

    // Ürün koşulu olmayan kampanya bütün ürünleri kapsar (sorgu daraltılmaz).
    $campaign->update(['product_scope' => 'all', 'product_rules' => null]);
    expect($covered())->toContain($this->product->id, $other->id);
});

it('applies combinable campaigns together and keeps the line cap', function () {
    $ten = kmpCampaign(['name' => '%10', 'action' => 'percent', 'action_settings' => ['rate' => 10]]);
    $twenty = kmpCampaign(['name' => '%20', 'action' => 'percent', 'action_settings' => ['rate' => 20]]);
    $shipping = kmpCampaign(['name' => 'Ücretsiz kargo', 'action' => 'shipping', 'action_settings' => [], 'shipping_mode' => 'free']);

    // Birleşme yokken yalnız en kazançlısı.
    expect(kmpCart([$this->product->id => 1])->totals()['campaign']['others'])->toBe(2);

    // %10 ile ücretsiz kargo birleşebilir (seçim iki yönlü yazılır).
    app(CampaignService::class)->save($ten, ['combinable' => [$shipping->id]], User::factory()->create());
    expect($shipping->fresh()->combinesWith($ten))->toBeTrue();

    $totals = kmpCart([$this->product->id => 1])->totals();
    $line = kmpCart([$this->product->id => 1])->lines()->first();

    // %10 + ücretsiz kargo (50 TL kazanç) > %20 tek başına.
    expect($totals['campaign']['name'])->toBe('%10 + Ücretsiz kargo')
        ->and($totals['discount'])->toEqualDecimal('10')
        ->and($totals['shipping'])->toEqualDecimal('0')
        ->and($totals['campaign']['shipping'])->toBe('Ücretsiz kargo')
        ->and($totals['campaign']['others'])->toBe(1)
        ->and($line['campaign'])->toBe('%10');

    // Aynı ürüne iki indirim: toplam satır tutarını geçmez (kargo kampanyası kapalı).
    $twenty->update(['action_settings' => ['rate' => 95]]);
    $shipping->update(['status' => Status::Passive]);
    app(CampaignService::class)->save($ten, ['combinable' => [$shipping->id, $twenty->id]], User::factory()->create());
    $line = kmpCart([$this->product->id => 1])->lines()->first();
    expect($line['discount'])->toEqualDecimal('100')
        ->and($line['campaign'])->toBe('%10 + %20');

    // Listeden çıkarılan kampanyanın karşı yönü de silinir.
    app(CampaignService::class)->save($ten, ['combinable' => []], User::factory()->create());
    expect($shipping->fresh()->combinesWith($ten))->toBeFalse();
});

it('saves combinable campaigns from the panel form', function () {
    $other = kmpCampaign(['name' => 'Ücretsiz kargo', 'action' => 'shipping', 'action_settings' => [], 'shipping_mode' => 'free']);
    $campaign = kmpCampaign();
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('panel.shop.campaigns.edit', $campaign))
        ->assertInertia(fn ($page) => $page->has('otherCampaigns', 1)->where('campaign.combinable', []));

    $this->put(route('panel.shop.campaigns.update', $campaign), [
        'name' => '2 Al 1 Öde', 'product_scope' => 'all', 'action' => 'buy_x_pay_y', 'settings' => ['buy' => 2, 'pay' => 1],
        'shipping_mode' => 'cart', 'combinable' => [$other->id, 'yok'],
    ])->assertSessionHasErrors('combinable.1');

    $this->put(route('panel.shop.campaigns.update', $campaign), [
        'name' => '2 Al 1 Öde', 'product_scope' => 'all', 'action' => 'buy_x_pay_y', 'settings' => ['buy' => 2, 'pay' => 1],
        'shipping_mode' => 'cart', 'combinable' => [$other->id],
    ])->assertSessionHasNoErrors();

    expect($campaign->combinesWith($other))->toBeTrue()
        ->and($other->combinesWith($campaign))->toBeTrue();
});

it('applies tiered discounts by line quantity or cart total', function () {
    $other = kmpProduct($this);
    $campaign = kmpCampaign(['name' => 'Kademeli', 'action' => 'tiered', 'action_settings' => [
        'basis' => 'line_quantity', 'tiers' => [['min' => '10', 'rate' => '10'], ['min' => '3', 'rate' => '5']],
    ]]);

    // Ürün başına adet: 3 adet %5, 10 adet %10, 2 adet indirimsiz.
    $lines = kmpCart([$this->product->id => 10, $other->id => 3])->lines()->keyBy(fn ($l) => $l['product']->id);
    expect($lines[$this->product->id]['discount'])->toEqualDecimal('100')
        ->and($lines[$other->id]['discount'])->toEqualDecimal('15')
        ->and(kmpCart([$this->product->id => 2])->totals()['campaign'])->toBeNull()
        ->and(kmpCart([$this->product->id => 3])->totals()['campaign']['summary'])->toBe('3 adet %5, 10 adet %10');

    // Sepet tutarı (KDV dahil): 1.000 TL üstü %5. 8 × 120 = 960 TL → yok; 9 × 120 = 1.080 TL → %5.
    $campaign->update(['action_settings' => ['basis' => 'cart_total', 'tiers' => [['min' => '1000', 'rate' => '5']]]]);
    expect(kmpCart([$this->product->id => 8])->totals()['campaign'])->toBeNull()
        ->and(kmpCart([$this->product->id => 9])->totals()['discount'])->toEqualDecimal('45');
});

it('validates tiers sent from the panel form as json', function () {
    $campaign = kmpCampaign();
    $this->actingAs(User::factory()->admin()->create());
    $payload = fn (string $tiers) => [
        'name' => 'Kademeli', 'product_scope' => 'all', 'action' => 'tiered', 'shipping_mode' => 'cart',
        'settings' => ['basis' => 'line_quantity', 'tiers' => $tiers],
    ];

    $this->put(route('panel.shop.campaigns.update', $campaign), $payload('[{"min":"3","rate":"150"}]'))
        ->assertSessionHasErrors('settings.tiers.0.rate');

    $this->put(route('panel.shop.campaigns.update', $campaign), $payload('[{"min":"3","rate":"5"},{"min":"10","rate":"7,5"}]'))
        ->assertSessionHasNoErrors();

    expect($campaign->fresh()->action_settings['tiers'])->toBe([['min' => '3', 'rate' => '5'], ['min' => '10', 'rate' => '7.5']]);
});

it('tells how much more to add for a cart total campaign or the next tier', function () {
    kmpCampaign(['name' => '1.000 TL üstü %10', 'action' => 'percent', 'action_settings' => ['rate' => 10],
        'audience_rules' => [[['field' => 'cart_total', 'operator' => 'gte', 'value' => '1000']]]]);

    // 1 × 120 TL (KDV dahil): 880 TL eksik.
    $totals = kmpCart([$this->product->id => 1])->totals();
    expect($totals['campaign'])->toBeNull()
        ->and($totals['nudge']['amount'])->toEqualDecimal('880')
        ->and($totals['nudge']['name'])->toBe('1.000 TL üstü %10');

    // Eşiğe ulaşınca teşvik yok.
    expect(kmpCart([$this->product->id => 9])->totals()['nudge'])->toBeNull();

    // Kademeli (sepet tutarı): uygulanırken bir üst kademe gösterilir.
    Campaign::query()->delete();
    kmpCampaign(['name' => 'Kademeli', 'action' => 'tiered', 'action_settings' => [
        'basis' => 'cart_total', 'tiers' => [['min' => '100', 'rate' => '5'], ['min' => '600', 'rate' => '10']],
    ]]);
    $totals = kmpCart([$this->product->id => 2])->totals();
    expect($totals['campaign']['name'])->toBe('Kademeli')
        ->and($totals['nudge']['amount'])->toEqualDecimal('360');
});

it('prices a single unit with the campaign for product cards', function () {
    kmpCampaign(['name' => '%10', 'action' => 'percent', 'action_settings' => ['rate' => 10]]);

    expect(app(CampaignEngine::class)->unitPriceFor($this->product, $this->try, null))->toEqualDecimal('90');

    // Sepet tutarı koşulu tek adetle sağlanmıyorsa kart fiyatı gösterilmez.
    Campaign::query()->update(['audience_rules' => [[['field' => 'cart_total', 'operator' => 'gte', 'value' => '1000']]]]);
    app()->forgetScopedInstances();
    expect(app(CampaignEngine::class)->unitPriceFor($this->product, $this->try, null))->toBeNull();
});
