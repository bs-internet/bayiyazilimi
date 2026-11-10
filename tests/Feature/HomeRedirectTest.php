<?php

declare(strict_types=1);

use App\Http\Middleware\SiteAccessGate;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * B2B çekirdekte vitrin yoktur: kök adres ziyaretçiyi girişe, oturum açmış
 * kullanıcıyı kendi alanına gönderir; kayıt kapalıdır. Bir modül (vitrin) `/`
 * rotasını ya da site erişimini değiştirdiyse bu testler atlanır.
 */
$coreHome = fn () => Route::getRoutes()->getByName('home')?->getActionName() === 'Closure';

it('sends guests from the root url to the login page', function () use ($coreHome) {
    if (! $coreHome()) {
        $this->markTestSkipped('Kök adresi bir modül sunuyor.');
    }

    $this->get(route('home'))->assertRedirect(route('login'));
});

it('sends signed-in users from the root url to their dashboard', function () use ($coreHome) {
    if (! $coreHome()) {
        $this->markTestSkipped('Kök adresi bir modül sunuyor.');
    }

    $this->actingAs(User::factory()->create())->get(route('home'))->assertRedirect(route('dashboard'));
});

it('keeps registration closed while the site is not public', function () {
    if (SiteAccessGate::isPublic()) {
        $this->markTestSkipped('Site bir modülle herkese açık.');
    }

    $this->get(route('register'))->assertRedirect(route('login'));
});
