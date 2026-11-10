<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kurulmamış sistemde her istek kurulum sihirbazına (/kurulum) gider;
 * kurulu sistemde sihirbaz kapalıdır (404).
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = app(InstallState::class)->installed();
        $installRoute = $request->is('kurulum', 'kurulum/*');

        if ($installed && $installRoute) {
            abort(404);
        }

        if (! $installed && ! $installRoute && ! $request->is('up')) {
            return redirect('/kurulum');
        }

        return $next($request);
    }
}
