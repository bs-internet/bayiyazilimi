<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\StockService;
use Database\Seeders\ShopSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Ödeme formunda ziyaretçinin giriş bağlantısı ve "bu bilgilerle üye ol" seçeneği.
 * Ziyaretçi ödemesi vitrindedir; çekirdek testinde aynı controller ziyaretçiye açık
 * bir test rotasına bağlanır.
 */
class GuestCheckoutTestController extends CheckoutController
{
    protected function routePrefix(): string
    {
        return 'shop.';
    }

    protected function page(): string
    {
        return 'shop/checkout/index';
    }

    protected function placedUrl(Order $order): string
    {
        return '/siparis-tamam/'.$order->number;
    }
}

beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);
    Carrier::query()->update(['status' => Status::Passive->value]);
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    Contract::query()->whereIn('slug', ['satis-sozlesmesi', 'uyelik-sozlesmesi', 'kvkk-aydinlatma-metni'])->update(['status' => Status::Active->value]);

    // Ziyaretçi ödemesi vitrinle gelir; vitrinsiz çekirdekte site ziyaretçiye açılır.
    hooks()->filter('site.public_access', fn () => true);

    Route::middleware('web')->post('/_test/odeme', [GuestCheckoutTestController::class, 'store']);

    $try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'UYE-1', 'name' => 'Üyelik Ürünü', 'slug' => 'uyelik-urunu', 'price' => 100,
        'currency_id' => $try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 5, StockMovementType::In);

    $this->fillCart = function (int $quantity = 1): void {
        Basket::cart()->set($this->product->fresh(), $quantity);
    };

    $this->payload = fn (array $extra = []) => [
        'email' => 'Yeni.Musteri@example.com',
        'phone' => '05550000000',
        'billing' => ['type' => 'individual', 'name' => 'Yeni Müşteri', 'address' => 'Adres 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => '1',
        'payment_method' => 'bank_transfer',
        'agreements' => ['satis-sozlesmesi' => '1', 'kvkk-aydinlatma-metni' => '1'],
        ...$extra,
    ];
});

it('offers the login link and registration only to visitors, without contracts already on the checkout form', function () {
    $props = CheckoutController::guestAccountProps(null);

    expect($props['login_url'])->toBe(route('shop.checkout-login'))
        ->and(array_column($props['contracts'], 'slug'))->toBe(['uyelik-sozlesmesi'])
        ->and(CheckoutController::guestAccountProps(User::query()->where('email', 'admin@admin.com')->first()))->toBeNull();
});

it('registers the visitor with the order, links the order to the new account and signs them in', function () {
    Event::fake([Registered::class]);
    ($this->fillCart)();

    $this->post('/_test/odeme', ($this->payload)([
        'create_account' => '1',
        'password' => 'GucluSifre!2026',
        'password_confirmation' => 'GucluSifre!2026',
        'account_agreements' => ['uyelik-sozlesmesi' => '1'],
        'marketing_consent' => '1',
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $user = User::query()->where('email', 'yeni.musteri@example.com')->sole();
    $order = Order::query()->withoutGlobalScopes()->latest()->first();

    expect($user->name)->toBe('Yeni Müşteri')
        ->and($user->user_type)->toBe(UserType::Member)
        ->and($user->status)->toBe(UserStatus::Draft)
        ->and($user->account_id)->not->toBeNull()
        ->and($user->marketing_consent_at)->not->toBeNull()
        ->and($order->user_id)->toBe($user->id)
        ->and($order->account_id)->toBe($user->account_id)
        ->and(CustomerAddress::query()->withoutGlobalScopes()->where('account_id', $user->account_id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class);
});

it('asks a visitor whose e-mail is already registered to sign in, and places no order', function () {
    User::factory()->create(['email' => 'yeni.musteri@example.com']);
    ($this->fillCart)();

    $this->post('/_test/odeme', ($this->payload)([
        'email' => 'yeni.musteri@example.com',
        'create_account' => '1',
        'password' => 'GucluSifre!2026',
        'password_confirmation' => 'GucluSifre!2026',
        'account_agreements' => ['uyelik-sozlesmesi' => '1'],
    ]))->assertSessionHasErrors(['email' => __('Bu e-posta adresiyle üyeliğiniz var; giriş yaparak devam edin.')]);

    expect(Order::query()->withoutGlobalScopes()->count())->toBe(0);
    $this->assertGuest();
});

it('requires the membership contract and a confirmed password only when the visitor wants an account', function () {
    ($this->fillCart)();

    $this->post('/_test/odeme', ($this->payload)(['create_account' => '1', 'password' => 'GucluSifre!2026']))
        ->assertSessionHasErrors(['password', 'account_agreements.uyelik-sozlesmesi']);

    // Üye olmadan sipariş: şifre ve üyelik sözleşmesi istenmez, kullanıcı açılmaz.
    $this->post('/_test/odeme', ($this->payload)())->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'yeni.musteri@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('does not open the account when the order cannot be placed', function () {
    ($this->fillCart)(9); // stokta 5 var

    $this->post('/_test/odeme', ($this->payload)([
        'create_account' => '1',
        'password' => 'GucluSifre!2026',
        'password_confirmation' => 'GucluSifre!2026',
        'account_agreements' => ['uyelik-sozlesmesi' => '1'],
    ]));

    expect(User::query()->where('email', 'yeni.musteri@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('sends the visitor to sign in and back to checkout afterwards', function () {
    $this->from('/odeme')->get(route('shop.checkout-login'))->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(url('/odeme'));

    // Başka siteden gelen geri dönüş adresi kabul edilmez.
    $this->from('https://kotu.example.com/x')->get(route('shop.checkout-login'));

    expect(session('url.intended'))->toBe(route('home'));
});
