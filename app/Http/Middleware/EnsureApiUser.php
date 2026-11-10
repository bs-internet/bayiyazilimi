<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST API: anahtarın sahibi etkin bir yönetici olmalı. Pasifleşen (ya da
 * yönetici olmayan) kullanıcının anahtarları çalışmaz; kısıtlı (taslak)
 * kullanıcının anahtarı yalnız okuma yapar. Silinen kullanıcının anahtarı
 * Sanctum'da zaten çözülmez (401).
 */
class EnsureApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->user_type->isAdmin() || ! $user->status->canLogin()) {
            return response()->json(['message' => __('API anahtarı geçersiz: anahtarın sahibi etkin bir yönetici değil.')], 401);
        }

        if ($user->status->isReadOnly() && ! $request->isMethodSafe()) {
            return response()->json(['message' => __('Kısıtlı kullanıcının API anahtarı yalnız okuma yapabilir.')], 403);
        }

        return $next($request);
    }
}
