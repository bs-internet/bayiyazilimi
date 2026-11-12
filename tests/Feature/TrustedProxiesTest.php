<?php

declare(strict_types=1);

use App\Support\Security\TrustedProxies;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

afterEach(fn () => TrustProxies::flushState());

test('trusted proxies: cloudflare expands to its ranges, star wins, empty means none', function () {
    expect(TrustedProxies::resolve(null))->toBeNull()
        ->and(TrustedProxies::resolve(' '))->toBeNull()
        ->and(TrustedProxies::resolve('10.0.0.1, *'))->toBe('*')
        ->and(TrustedProxies::resolve('cloudflare,10.0.0.1'))->toContain('162.158.0.0/15', '2606:4700::/32', '10.0.0.1');
});

test('behind cloudflare the client ip comes from the forwarded header only for cloudflare addresses', function () {
    Route::get('/_test/ip', fn () => request()->ip());
    TrustProxies::at(TrustedProxies::resolve('cloudflare'));

    $this->withServerVariables(['REMOTE_ADDR' => '162.158.10.20'])
        ->withHeader('X-Forwarded-For', '88.1.2.3')
        ->get('/_test/ip')->assertSee('88.1.2.3');

    $this->withServerVariables(['REMOTE_ADDR' => '5.6.7.8'])
        ->withHeader('X-Forwarded-For', '88.1.2.3')
        ->get('/_test/ip')->assertSee('5.6.7.8');
});
