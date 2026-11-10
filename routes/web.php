<?php

declare(strict_types=1);

use App\Http\Controllers\CspReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Panel\Members\MembersController;
use App\Http\Controllers\Panel\Settings\User\UsersController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Kök adres: B2B çekirdekte vitrin yoktur; giriş yapan kendi alanına, ziyaretçi
// giriş sayfasına gider. Vitrin modülü bu rotayı (aynı ad: `home`) ana sayfayla
// değiştirir (aşağıdaki `web.routes.register`).
Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');

// Tarayıcının CSP ihlal bildirimleri (SetSecurityHeaders, report-uri); oturumsuz da gelir.
Route::post('csp-report', CspReportController::class)->middleware('throttle:60,1')->name('csp.report');

// İl / ilçe listeleri (adres formları, ödeme, Kurumsal Bilgiler). Herkese açık
// veri; misafir ödemesi de kullandığı için oturum istemez.
Route::controller(LocationController::class)->prefix('konum')->name('locations.')
    ->middleware('throttle:120,1')
    ->group(function () {
        Route::get('iller/{country}', 'countryCities')->name('cities');
        Route::get('ilceler/{city}', 'districts')->name('districts');
    });

Route::get('/panel/settings/users/{user}/email/change/confirm', [UsersController::class, 'confirmEmailChange'])
    ->middleware('signed')
    ->name('panel.settings.users.email.confirm');

Route::get('/panel/settings/users/{user}/welcome', [UsersController::class, 'acceptWelcome'])
    ->middleware('signed')
    ->name('panel.settings.users.welcome');

Route::get('/panel/members/{user}/email/change/confirm', [MembersController::class, 'confirmEmailChange'])
    ->middleware('signed')
    ->name('panel.members.email.confirm');

Route::get('/panel/members/{user}/welcome', [MembersController::class, 'acceptWelcome'])
    ->middleware('signed')
    ->name('panel.members.welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        // Bir eklenti kullanıcıya kendi alanını verebilir (ör. plasiyer ekranı): `auth.home` filtresi.
        $home = hooks()->apply('auth.home', null, user: request()->user());

        if (is_string($home) && $home !== '') {
            return redirect($home);
        }

        $userType = request()->user()->user_type;

        if ($userType->isAdmin()) {
            return redirect()->route('panel.dashboard');
        }

        if ($userType->isMember()) {
            return redirect()->route('app.dashboard');
        }

        // Supplier or any other type not allowed
        Auth::logout();

        return redirect()->route('login')->with('toast', ['type' => 'error', 'message' => __('auth.must_login')]);
    })->name('dashboard');
});

// robots.txt: çekirdek (B2B) herkese açık içerik sunmaz, tümüyle kapalıdır. Herkese
// açık sayfası olan modül (vitrin) içeriği `seo.robots` filtresiyle değiştirir.
Route::get('robots.txt', fn () => response(
    (string) hooks()->apply('seo.robots', "User-agent: *\nDisallow: /\n"),
    200,
    ['Content-Type' => 'text/plain; charset=UTF-8'],
))->name('robots');

// Modüllerin herkese açık rotaları (web grubu). En sonda tetiklenir ki bir modül
// çekirdeğin bir rotasını (ör. `/` → `home`) aynı adla değiştirebilsin.
hooks()->do('web.routes.register');
