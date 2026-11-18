<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Shop\ShopSettings;
use Bsinternet\Vitrin\Http\Middleware\ShareStorefrontData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Gizli mağaza ayarları (ödeme sağlayıcısı anahtarları vb.) toplu ayar dizisine
 * (ShopSettings::all → vitrin sayfa verisi) girmez ve veritabanında şifreli durur.
 */

beforeEach(function () {
    // Örnek bir ödeme eklentisinin ayarları: biri filtreyle bildirilmiş, biri adından gizli.
    hooks()->filter('shop.settings.defaults', fn (array $value) => [...$value,
        'ornekpos_enabled' => '0',
        'ornekpos_merchant_id' => '',
        'ornekpos_merchant_key' => '',
        'ornekpos_signature' => '',
    ]);
    hooks()->filter('shop.settings.secret_keys', fn (array $value) => [...$value, 'ornekpos_signature']);
    Once::flush();
});

test('secret settings never appear in the bulk settings array', function () {
    ShopSettings::save([
        'ornekpos_merchant_id' => '123456',
        'ornekpos_merchant_key' => 'gizli-anahtar',
        'ornekpos_signature' => 'gizli-imza',
    ]);

    $all = ShopSettings::all();

    expect($all)->toHaveKey('ornekpos_merchant_id', '123456')
        ->and($all)->not->toHaveKey('ornekpos_merchant_key')
        ->and($all)->not->toHaveKey('ornekpos_signature')
        ->and(ShopSettings::secretKeys())->toContain('ornekpos_merchant_key', 'ornekpos_signature')
        ->and(ShopSettings::get('ornekpos_merchant_key'))->toBe('gizli-anahtar')
        ->and(ShopSettings::get('ornekpos_signature'))->toBe('gizli-imza');
});

test('secret settings are stored encrypted and an empty value keeps the saved one', function () {
    ShopSettings::save(['ornekpos_merchant_key' => 'gizli-anahtar']);

    $stored = (string) DB::table('settings')->where('key', 'ornekpos_merchant_key')->value('value');

    expect($stored)->toStartWith('enc:v1:')
        ->and($stored)->not->toContain('gizli-anahtar');

    ShopSettings::save(['ornekpos_merchant_key' => '']);

    expect(ShopSettings::get('ornekpos_merchant_key'))->toBe('gizli-anahtar');
});

test('a plain value saved before encryption is still read, and the migration encrypts it', function () {
    Setting::query()->create(['key' => 'ornekpos_merchant_key', 'value' => 'eski-duz-deger', 'group' => ShopSettings::GROUP]);

    expect(ShopSettings::get('ornekpos_merchant_key'))->toBe('eski-duz-deger');

    (require database_path('migrations/2026_11_18_100000_encrypt_secret_shop_settings.php'))->up();

    expect((string) DB::table('settings')->where('key', 'ornekpos_merchant_key')->value('value'))->toStartWith('enc:v1:')
        ->and(ShopSettings::get('ornekpos_merchant_key'))->toBe('eski-duz-deger');
});

test('a module that encrypts its own secret still reads it back', function () {
    // Eklenti kendi katmanını ekler (Crypt), çekirdek bir katman daha ekler; get() yalnız kendi katmanını açar.
    ShopSettings::save(['ornekpos_merchant_key' => Crypt::encryptString('eklentinin-sifresi')]);

    expect(Crypt::decryptString((string) ShopSettings::get('ornekpos_merchant_key')))->toBe('eklentinin-sifresi');
});

test('the storefront page data carries no secret settings', function () {
    ShopSettings::save(['ornekpos_merchant_key' => 'gizli-anahtar', 'ornekpos_merchant_id' => '123456']);

    $this->withoutVite()->get(route('home'))
        ->assertOk()
        ->assertDontSee('gizli-anahtar')
        ->assertInertia(fn (Assert $page) => $page
            ->where('storefront.settings.ornekpos_merchant_id', '123456')
            ->missing('storefront.settings.ornekpos_merchant_key'));
})->skip(fn () => ! class_exists(ShareStorefrontData::class), 'Vitrin eklentisi kurulu değil.');
