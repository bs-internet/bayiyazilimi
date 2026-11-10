<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Panel\Settings\Roles\RoleService;
use App\Support\Permissions\PermissionDependencies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel rotalarına otomatik yetki kontrolü uygular: rota adı = permission adı.
 *
 * Super Admin Gate::before üzerinden hepsini geçer. Diğer kullanıcılar yalnızca
 * kendilerine atanmış izinli rotalara erişebilir. Burası dışında ek bir middleware
 * koymaya gerek yok — yeni rota eklemek tek başına onu otomatik korumaya alır.
 *
 * Yeni keşfedilen rotalarda izin DB'de henüz yoksa yalnız Super Admin erişebilir;
 * admin "Yetkiler" UI'sinden "Rotalardan keşfet" ile o izni ekler ve istenen
 * rollere atayabilir.
 */
class EnsureRoutePermission
{
    /**
     * Yetki kontrolünden muaf rota adları (giriş noktaları vb.).
     */
    private const EXEMPT_ROUTE_NAMES = [
        'panel.dashboard',
        // Bölüm giriş sayfaları: kartlar menüden, yetkiye göre süzülmüş gelir.
        'panel.tools.definitions.index',
        'panel.settings.general.overview',
    ];

    /**
     * Yetki kontrolünden muaf rota ön ekleri. Kişiye özel alanlar (kendi profili,
     * güvenlik, oturumlar, bildirimler) izinle yönetilmez; her panel kullanıcısı
     * kendi bilgilerine erişebilir.
     */
    private const EXEMPT_ROUTE_PREFIXES = [
        'panel.profile.',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        $user = $request->user();

        // Adı olmayan rotanın yetkisi tanımlanamaz: yalnız Super Admin açar,
        // diğerleri açıklamalı bir uyarıyla geldikleri sayfaya döner.
        if ($name === null) {
            return $user?->hasRole(RoleService::SUPER_ADMIN) ? $next($request) : $this->unnamedRouteDenied($request);
        }

        if ($this->isExempt($name)) {
            return $next($request);
        }

        abort_unless($user !== null && $user->can($name), 403);

        // Bölümü görme yetkisi olmadan o bölümdeki işlem yapılamaz (ör. sipariş
        // detayı olmadan fatura güncelleme).
        abort_unless(app(PermissionDependencies::class)->satisfiedBy($user, $name), 403);

        return $next($request);
    }

    private function unnamedRouteDenied(Request $request): Response
    {
        $message = __('Erişmek istediğiniz sayfa tanımlı değil.');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        // Aynı sayfaya geri dönmek döngü olur: önceki adres bu sayfaysa panele gidilir.
        $previous = url()->previous();
        $target = $previous !== '' && $previous !== $request->fullUrl() ? $previous : route('panel.dashboard');

        return redirect()->to($target)->with('toast', ['type' => 'warning', 'message' => $message]);
    }

    private function isExempt(string $name): bool
    {
        if (in_array($name, self::EXEMPT_ROUTE_NAMES, true)) {
            return true;
        }

        foreach (self::EXEMPT_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
