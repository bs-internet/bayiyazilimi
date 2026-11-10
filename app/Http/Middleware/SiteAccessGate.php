<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Shop\ShopSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Site erişimi (web grubunda, ShopServiceProvider ekler):
 *
 * - Bakım modu: yöneticiler dışında herkes bakım sayfasını görür (503).
 *   Oturum sayfaları açık kalır ki yönetici giriş yapabilsin.
 * - Yalnız bayiler (çekirdeğin varsayılanı, B2B): ziyaretçi oturum sayfasına
 *   düşer, kayıt kapanır, müşteri hesaplı üyenin oturumu kapatılır. Bayiler
 *   ve yöneticiler normal kullanır. Site herkese ancak bir modül açarsa açılır
 *   (`site.public_access`; vitrin modülü kurulu ve etkinken).
 */
class SiteAccessGate
{
    /**
     * Her durumda açık kalan yollar (panel, oturum akışları, ödeme bildirimi, sağlık kontrolü).
     */
    public const ALWAYS_OPEN_PATHS = [
        'panel', 'panel/*',
        'login', 'logout', 'two-factor-challenge',
        'forgot-password', 'reset-password', 'reset-password/*',
        'email/verify', 'email/verify/*', 'email/verification-notification',
        'user/confirm-password', 'user/confirmed-password-status', 'user/two-factor-*',
        'up', 'storage/*', 'build/*',
        // Tarayıcı kuralları: bakımda ve kapalı sitede "Disallow: /" döner.
        'robots.txt',
        // Tarayıcının CSP ihlal bildirimi (oturum sayfalarından da gelir).
        'csp-report',
        // İl / ilçe listeleri: herkese açık veri (adres formları, ödeme).
        'konum/*',
        // Ödeme sağlayıcısı bildirimi ve 3D dönüşü.
        'odeme/bildirim/*', 'odeme/donus/*', 'odeme/kart/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isAdmin = $user instanceof User && $user->user_type->isAdmin();

        if ($isAdmin || $request->is(...self::ALWAYS_OPEN_PATHS)) {
            return $next($request);
        }

        $signed = self::isSignedAccountLink($request);

        // Bakımda imzalı bağlantı yalnız görüntülenir; işlem (teklif onayı, sipariş) yapılamaz.
        if (ShopSettings::bool('maintenance_mode') && ! ($signed && $request->isMethodSafe())) {
            return $this->maintenance($request);
        }

        if ($signed) {
            return $next($request);
        }

        if (! self::isPublic()) {
            return $this->membersOnly($request, $user, $next);
        }

        return $next($request);
    }

    /**
     * Site ziyaretçilere açık mı? Çekirdekte hayır (B2B: yalnız oturum açmış
     * üyeler); vitrin gibi bir modül `site.public_access` filtresiyle açar.
     */
    public static function isPublic(): bool
    {
        return (bool) hooks()->apply('site.public_access', false);
    }

    private function maintenance(Request $request): Response
    {
        return Inertia::render('maintenance', [
            'message' => ShopSettings::get('maintenance_message'),
        ])->toResponse($request)->setStatusCode(503)->header('Retry-After', '3600');
    }

    /**
     * Site kapalıyken (B2B): kayıt yok, ziyaretçi oturum sayfasına düşer.
     * Müşteri hesaplı üyenin (ör. vitrin kapatılınca kalan müşteriler) oturumu
     * kapatılır; bayiler geçer.
     */
    private function membersOnly(Request $request, ?User $user, Closure $next): Response
    {
        if ($request->is('register')) {
            return redirect()->route('login');
        }

        if ($user === null) {
            // Üye alanı ve hesap sayfaları kendi auth yönlendirmesini yapar; diğerleri doğrudan girişe.
            return $request->is('app', 'app/*') ? $next($request) : redirect()->guest(route('login'));
        }

        $user->loadMissing('account');

        // Hesabı olmayan üye (henüz tanımlanmamış) zaten hiçbir kayıt göremez; ona dokunulmaz.
        if ($user->user_type->isMember() && $user->account !== null && ! $user->isDealer()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('toast', [
                'type' => 'warning',
                'message' => __('Bu site yalnızca bayilerimize açıktır.'),
            ]);
        }

        return $next($request);
    }

    /**
     * İmzalı bağlantılar site kapalıyken de açılır: hoş geldin / e-posta onayı
     * (yeni bayinin şifre belirlemesi) ve modüllerin eklediği rotalar
     * (`site.signed_routes`; ör. vitrinin misafir teklif / sipariş bağlantıları).
     */
    public static function isSignedAccountLink(Request $request): bool
    {
        return $request->routeIs(
            'panel.*.welcome', 'panel.*.email.confirm', 'panel.*.*.welcome', 'panel.*.*.email.confirm',
            ...hooks()->apply('site.signed_routes', []),
        ) && $request->hasValidSignature();
    }
}
