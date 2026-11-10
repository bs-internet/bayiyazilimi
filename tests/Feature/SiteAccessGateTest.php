<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Shop\ShopSettings;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Site erişimi: vitrin modülü yoksa (ya da kapatıldıysa) site yalnız bayilere
 * açıktır. Modül kuruluyken de bu davranışı sınamak için `site.public_access`
 * en son çalışan bir filtreyle kapatılır.
 */
beforeEach(function () {
    $this->withoutVite();
    hooks()->filter('site.public_access', fn () => false, PHP_INT_MAX);
});

it('logs out a customer account while the site is dealers only', function () {
    $customer = sampleCustomer();

    $this->actingAs($customer)->get(route('app.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast.type', 'warning');
    $this->assertGuest();
});

it('lets dealers in while the site is dealers only', function () {
    $dealer = User::query()->where('email', 'user@user.com')->firstOrFail();

    $this->actingAs($dealer)->get(route('app.dashboard'))->assertOk();
});

it('manages only the maintenance mode on the site access page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('panel.shop.site-access.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/settings/general/access')
            ->has('settings.maintenance_mode')
            ->missing('settings.dealers_only')
            ->missing('storefront'));

    $this->actingAs($admin)->put(route('panel.shop.site-access.update'), [
        'maintenance_mode' => '1', 'maintenance_message' => 'Bakımdayız.',
    ])->assertSessionHasNoErrors();

    expect(ShopSettings::bool('maintenance_mode'))->toBeTrue()
        ->and(ShopSettings::get('maintenance_message'))->toBe('Bakımdayız.');

    ShopSettings::save(['maintenance_mode' => '0']);
});
