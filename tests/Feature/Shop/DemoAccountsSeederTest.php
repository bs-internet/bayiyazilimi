<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Support\Facades\Hash;

it('converts sample accounts to demo accounts and discovers route permissions', function () {
    $dealer = User::query()->where('email', 'user@user.com')->firstOrFail();
    // Örnek veriler müşteri hesabını yalnız vitrinle açar (çekirdekte giriş yapamayan ölü hesap olurdu).
    expect(User::query()->where('email', 'musteri@musteri.com')->exists())->toBe(CustomerType::customersEnabled());
    // Bu senaryo çekirdeği sınar: vitrin kuruluyken de yalnız bayi.
    hooks()->filter('shop.customer_types', fn () => [CustomerType::Dealer], PHP_INT_MAX);
    User::query()->where('email', 'musteri@musteri.com')->delete();
    Permission::query()->where('name', 'panel.shop.stock-settings.edit')->forceDelete();

    $this->seed(DemoAccountsSeeder::class);

    expect(User::query()->whereIn('email', ['admin@admin.com', 'panel@admin.com', 'user@user.com'])->exists())->toBeFalse();

    $users = User::query()->with('account')->where('email', 'like', '%@bayiyazilimi-demo.com')->get()->keyBy('email');
    // Çekirdekte müşteri ve İçerik Editörü demo hesabı yok (vitrinle gelir).
    expect($users->keys()->sort()->values()->all())->toBe(['bayi@bayiyazilimi-demo.com', 'super@bayiyazilimi-demo.com', 'yonetici@bayiyazilimi-demo.com'])
        ->and(Hash::check('BayiDemo.123!', $users['bayi@bayiyazilimi-demo.com']->password))->toBeTrue()
        // Eski bayi hesabı taşındı: aynı kayıt, aynı hesap.
        ->and($users['bayi@bayiyazilimi-demo.com']->id)->toBe($dealer->id)
        ->and($users['bayi@bayiyazilimi-demo.com']->isDealer())->toBeTrue()
        ->and($users['yonetici@bayiyazilimi-demo.com']->hasRole('Super Admin'))->toBeTrue()
        ->and($users['super@bayiyazilimi-demo.com']->hasRole('Super Admin'))->toBeTrue()
        ->and(Permission::query()->where('name', 'panel.shop.stock-settings.edit')->exists())->toBeTrue();

    // Tekrar çalıştırılabilir.
    $this->seed(DemoAccountsSeeder::class);
    expect(User::query()->where('email', 'like', '%@bayiyazilimi-demo.com')->count())->toBe(3);

    $this->post(route('login.store'), ['email' => 'bayi@bayiyazilimi-demo.com', 'password' => 'BayiDemo.123!'])->assertRedirect();
    $this->assertAuthenticatedAs($users['bayi@bayiyazilimi-demo.com']);
});

it('adds the demo customer only while customer accounts exist (vitrin)', function () {
    hooks()->filter('shop.customer_types', fn () => [CustomerType::Dealer, CustomerType::Customer], PHP_INT_MAX);
    sampleCustomer();

    $this->seed(DemoAccountsSeeder::class);

    $customer = User::query()->where('email', 'musteri@bayiyazilimi-demo.com')->firstOrFail();
    expect($customer->isDealer())->toBeFalse()
        ->and(User::query()->where('email', 'musteri@musteri.com')->exists())->toBeFalse();
});
