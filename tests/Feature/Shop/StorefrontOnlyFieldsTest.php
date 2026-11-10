<?php

declare(strict_types=1);

use App\Enums\Status;
use App\Models\Category;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\ShopSettings;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Yalnız vitrinde işe yarayan alanlar (stoksuz ürün gösterimi, kategori SEO'su,
 * ETBİS, kampanya etiketi, sayfanın bayi panelinde gösterimi) vitrin modülü
 * yokken ekranda çıkmaz ve kayıtlı değerlerine dokunulmaz. Modül kuruluyken de
 * sınamak için `shop.storefront_enabled` en son çalışan filtreyle kapatılır.
 */
beforeEach(function () {
    $this->withoutVite();
    hooks()->filter('shop.storefront_enabled', fn () => false, PHP_INT_MAX);
    $this->admin = User::factory()->admin()->create();
});

it('hides the storefront-only fields on the panel screens', function () {
    foreach (['panel.shop.stock-settings.edit', 'panel.shop.categories.index', 'panel.shop.corporate.edit', 'panel.shop.campaigns.create'] as $route) {
        $this->actingAs($this->admin)->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('storefront', false));
    }
});

it('keeps the stored storefront-only values when saving without the storefront', function () {
    ShopSettings::save(['show_out_of_stock' => '1']);
    $warehouse = Warehouse::default() ?? Warehouse::query()->firstOrFail();

    $this->actingAs($this->admin)->put(route('panel.shop.stock-settings.update'), [
        'default_warehouse_id' => $warehouse->id, 'low_stock_threshold' => '5',
    ])->assertSessionHasNoErrors();

    expect(ShopSettings::bool('show_out_of_stock'))->toBeTrue();

    $category = Category::query()->create(['name' => 'SEO Kategori', 'slug' => 'seo-kategori', 'status' => Status::Active, 'meta_title' => 'Eski başlık']);

    $this->actingAs($this->admin)->post(route('panel.shop.categories.update', $category->id), [
        'name' => 'SEO Kategori 2', 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($category->fresh())->name->toBe('SEO Kategori 2')->meta_title->toBe('Eski başlık');
});
