<?php

declare(strict_types=1);

use App\Enums\BillingType;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('member can view account page', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->get(route('app.account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/account/index')
            ->has('countries')
            ->has('cities')
            ->has('districts')
            ->has('billingTypes', 2),
        );
});

test('member can update account information', function () {
    $user = User::factory()->member()->create();
    $district = District::query()->firstOrFail();
    $city = City::query()->findOrFail($district->city_id);
    $country = Country::query()->findOrFail($city->country_id);

    $this->actingAs($user)
        ->patch(route('app.account.update'), [
            'billing_type' => 'corporate',
            'title' => 'Test Firma',
            'tax_office' => 'Kadıköy',
            'tax_number' => '1234567890',
            'identity_number' => '12345678901',
            'invoice_email' => 'fatura@example.com',
            'address' => 'Test adres',
            'postal_code' => '34000',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $account = $user->fresh()->account;

    expect($account)->not->toBeNull()
        ->and($account->title)->toBe('Test Firma')
        ->and($account->billing_type)->toBe(BillingType::Corporate)
        ->and($account->tax_office)->toBe('Kadıköy')
        ->and($account->tax_number)->toBe('1234567890')
        // Kurumsal faturada TC kimlik no saklanmaz.
        ->and($account->identity_number)->toBeNull()
        ->and($account->invoice_email)->toBe('fatura@example.com');

    $this->assertDatabaseHas('addresses', [
        'account_id' => $account->id,
        'country_id' => $country->id,
        'city_id' => $city->id,
        'district_id' => $district->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'account',
        'event' => 'updated',
    ]);
});

test('account update creates account when missing', function () {
    $user = User::factory()->member()->create();

    expect($user->account)->toBeNull();

    $this->actingAs($user)
        ->patch(route('app.account.update'), [
            'billing_type' => 'individual',
            'title' => 'Yeni Hesap',
            'identity_number' => '12345678901',
            'address' => null,
            'postal_code' => null,
            'country_id' => null,
            'city_id' => null,
            'district_id' => null,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->fresh()->account)->not->toBeNull();
});

test('district must belong to selected city', function () {
    $user = User::factory()->member()->create();
    $country = Country::factory()->create(['code' => 'US']);
    $city = City::factory()->create(['country_id' => $country->id, 'code' => 'NY']);
    $otherCity = City::factory()->create(['country_id' => $country->id, 'code' => 'CA']);
    $district = District::factory()->create(['city_id' => $otherCity->id]);

    $this->actingAs($user)
        ->from(route('app.account'))
        ->patch(route('app.account.update'), [
            'billing_type' => 'individual',
            'title' => 'Test Firma',
            'identity_number' => '12345678901',
            'address' => 'Test adres',
            'postal_code' => '34000',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
        ])
        ->assertSessionHasErrors('district_id')
        ->assertRedirect(route('app.account'));
});

test('individual billing requires identity number and corporate requires tax info', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->from(route('app.account'))
        ->patch(route('app.account.update'), [
            'billing_type' => 'individual',
            'title' => 'Ad Soyad',
        ])
        ->assertSessionHasErrors('identity_number');

    $this->actingAs($user)
        ->from(route('app.account'))
        ->patch(route('app.account.update'), [
            'billing_type' => 'corporate',
            'title' => 'Firma',
        ])
        ->assertSessionHasErrors(['tax_office', 'tax_number']);
});

test('admin cannot access app account page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('app.account'))
        ->assertRedirect(route('dashboard'));
});
