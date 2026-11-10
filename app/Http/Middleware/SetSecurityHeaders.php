<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Görünüm çizilmeden önce: @vite etiketleri ve satır içi betikler bu nonce'u taşır.
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Konum yalnız kendi sayfalarımıza açık (ör. plasiyer ziyaret kaydı); tarayıcı yine izin sorar.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $this->setContentSecurityPolicy($response);

        return $response;
    }

    private function setContentSecurityPolicy(Response $response): void
    {
        $header = ContentSecurityPolicy::headerName();

        if ($header === null || $response->headers->has($header)) {
            return;
        }

        // Geliştirmede Vite sunucusu (başka port, websocket) ve hata ayıklama
        // sayfasının satır içi betikleri kuralın dışında kalır.
        if ((app()->isLocal() && Vite::isRunningHot()) || (config('app.debug') && $response->getStatusCode() >= 500)) {
            return;
        }

        $response->headers->set($header, ContentSecurityPolicy::header(
            (string) Vite::cspNonce(),
            Route::has('csp.report') ? route('csp.report', absolute: false) : null,
        ));
    }
}
