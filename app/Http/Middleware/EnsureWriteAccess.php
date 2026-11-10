<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for ensuring user has write access.
 *
 * Checks the authenticated user's status and prevents
 * write operations for users with read-only status.
 */
class EnsureWriteAccess
{
    /**
     * HTTP methods that require write access.
     *
     * @var array<string>
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Kısıtlı (salt okunur) kullanıcının da yapabileceği kişisel güvenlik /
     * tercih işlemleri: kendi şifresi, bildirimini okundu saymak, oturumlarını
     * kapatmak, görünüm tercihi. İş verisi değiştirmezler.
     *
     * @var array<int, string>
     */
    private const PERSONAL_ROUTES = [
        '*.profile.password.update',
        '*.profile.notifications.read',
        '*.profile.sessions.destroy',
        '*.profile.appearance.update',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->isWriteRequest($request) || $request->routeIs(...self::PERSONAL_ROUTES)) {
            return $next($request);
        }

        if ($user->status->isReadOnly()) {
            return $this->denyWriteAccess($request, __('auth.account_read_only'));
        }

        return $next($request);
    }

    private function denyWriteAccess(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }

    /**
     * Check if the request is a write request.
     */
    private function isWriteRequest(Request $request): bool
    {
        return in_array(strtoupper($request->method()), self::WRITE_METHODS);
    }
}
