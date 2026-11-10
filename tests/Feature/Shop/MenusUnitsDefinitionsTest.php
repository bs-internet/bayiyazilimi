<?php

declare(strict_types=1);

use App\Enums\Shop\RateSource;
use App\Enums\Status;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Services\Shop\ShopSettings;
use App\Support\Shop\SystemDefaults;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function menuTestProduct(array $attributes = []): Product
{
    $currency = Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'Türk Lirası', 'symbol' => '₺', 'decimal_places' => 2, 'thousands_separator' => '.',
        'decimal_separator' => ',', 'exchange_rate' => 1, 'rate_source' => RateSource::Manual,
        'status' => Status::Active, 'sort_order' => 1,
    ]);

    return Product::query()->create(array_merge([
        'sku' => 'M-'.fake()->unique()->numerify('####'),
        'name' => 'Menü Test Ürün',
        'slug' => 'menu-test-urun-'.fake()->unique()->numerify('####'),
        'price' => 100,
        'currency_id' => $currency->id,
        'tax_rate' => 20,
        'sale_mode' => 'cart',
        'unit' => 'Adet',
        'status' => Status::Active,
    ], $attributes));
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

test('units are defined under definitions and used by the product form', function () {
    $this->actingAs($this->admin)->post(route('panel.shop.units.store'), ['name' => 'Bidon'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('panel.shop.units.store'), ['name' => 'Bidon'])->assertSessionHasErrors('name');

    $unit = Unit::query()->where('name', 'Bidon')->firstOrFail();
    $product = menuTestProduct(['unit' => 'Bidon']);

    // Ad değişince ürünlerdeki birim de değişir.
    $this->actingAs($this->admin)->put(route('panel.shop.units.update', $unit->id), ['name' => 'Bidon (20 L)'])->assertSessionHasNoErrors();
    expect($product->fresh()->unit)->toBe('Bidon (20 L)');

    // Kullanılan birim silinemez.
    $this->actingAs($this->admin)->delete(route('panel.shop.units.destroy', $unit->id));
    expect(Unit::query()->whereKey($unit->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->get(route('panel.shop.units.index'))
        ->assertInertia(fn (Assert $p) => $p->component('panel/tools/definitions/units/index')
            ->where('units', fn ($units) => collect($units)->firstWhere('name', 'Bidon (20 L)')['products_count'] === 1));

    $this->actingAs($this->admin)->get(route('panel.shop.products.create'))
        ->assertInertia(fn (Assert $p) => $p->where('units', fn ($units) => collect($units)->pluck('value')->contains('Bidon (20 L)')));
});

test('general settings defaults are used: tax rate for new products and country for addresses', function () {
    $tax = Tax::query()->create(['name' => 'KDV %10', 'rate' => 10, 'status' => Status::Active]);
    Setting::query()->updateOrCreate(['key' => 'default_tax_id'], ['value' => $tax->id, 'group' => 'defaults']);

    $this->actingAs($this->admin)->get(route('panel.shop.products.create'))
        ->assertInertia(fn (Assert $p) => $p->where('defaults.tax_rate', '10'));

    $country = Country::query()->create(['code' => 'DE', 'name' => 'Almanya', 'status' => Status::Active]);
    Setting::query()->updateOrCreate(['key' => 'default_country_id'], ['value' => $country->id, 'group' => 'defaults']);

    expect(SystemDefaults::countryId())->toBe($country->id);
});

test('activity, cache and definitions sit under settings and section links open overview pages', function () {
    $admin = User::factory()->admin()->create();

    $this->withoutVite()->actingAs($admin)->get(route('panel.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('navigation', function ($groups) {
            $groups = collect($groups);
            $settings = collect($groups->firstWhere('key', 'settings')['items'])->keyBy('key');
            $definitions = $settings['panel.tools.definitions'];

            return $groups->firstWhere('key', 'tools') === null
                && $settings->has(['panel.tools.activity', 'panel.tools.cache'])
                && $definitions['collapsible'] === false
                && $definitions['href'] === route('panel.tools.definitions.index')
                && $settings['panel.settings.general']['href'] === route('panel.settings.general.overview')
                && collect($definitions['items'])->pluck('key')->contains('panel.shop.units.index')
                && $settings['panel.settings.users']['collapsible'] === false
                && $settings['panel.settings.users']['href'] === route('panel.settings.users.index')
                && $settings['panel.settings.general']['collapsible'] === false
                // Çekirdeğin öğeleri; modüller (ör. vitrin: Çerez Yönetimi) sona ekleyebilir.
                && collect($settings['panel.settings.general']['items'])->pluck('key')->take(4)->all() === ['panel.settings.general.overview', 'panel.settings.general.edit', 'panel.shop.corporate.edit', 'panel.shop.site-access.edit'];
        }));
});

test('overview pages list section summaries and warn about missing setup', function () {
    $admin = User::factory()->admin()->create();
    ShopSettings::save(['bank_transfer_enabled' => '1', 'maintenance_mode' => '1']);

    $this->withoutVite()->actingAs($admin)->get(route('panel.tools.definitions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/tools/definitions/overview')
            ->where('summaries', fn ($s) => $s['panel.shop.bank-accounts.index']['warning'] === true
                && $s['panel.shop.units.index']['text'] !== ''
                && $s['panel.tools.definitions.languages.index']['text'] !== ''));

    $this->actingAs($admin)->get(route('panel.settings.general.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/settings/general/overview')
            ->where('summaries', fn ($s) => $s['panel.shop.site-access.edit']['warning'] === true
                && $s['panel.settings.general.edit']['text'] !== ''));

    ShopSettings::save(['maintenance_mode' => '0']);
});
