<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Laravel Sanctum — REST API anahtarları
|--------------------------------------------------------------------------
|
| Bayi Yazılımı Sanctum'u yalnız kişisel erişim anahtarlarıyla (Bearer) kullanır:
| `guard` boştur, yani API istekleri tarayıcı oturumuyla kimlik doğrulamaz
| (oturumla gelen "geçici anahtar" tüm yetkileri geçeceği için kapalıdır).
| Anahtarlar Ayarlar → API Anahtarları ekranından oluşturulur; süre sınırı
| anahtar başına (`expires_at`) verilir.
|
*/

return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => [],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'bayi_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],

];
