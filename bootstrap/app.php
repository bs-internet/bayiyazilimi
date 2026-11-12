<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\BindCurrentAccount;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureApiUser;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\EnsureWriteAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetSecurityHeaders;
use App\Support\Install\InstallState;
use App\Support\Modules\ModulePackageManifest;
use App\Support\Modules\ModuleState;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // REST API (/api/v1, Sanctum anahtarları); bkz. routes/api.php.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('panel')
                ->name('panel.')
                ->group(base_path('routes/panel.php'));

            Route::middleware('web')
                ->prefix('app')
                ->name('app.')
                ->group(base_path('routes/app.php'));

            // Kurulum sihirbazı yalnız kurulmamış sistemde yüklenir. Konsolda (Wayfinder
            // üretimi, testler) her zaman yüklenir; kurulu sistemde EnsureInstalled yine 404 verir.
            if (app()->runningInConsole() || ! app(InstallState::class)->installed()) {
                Route::middleware('installer')->group(base_path('routes/install.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'user_type' => EnsureUserType::class,
            'active_user' => EnsureActiveUser::class,
            'write_access' => EnsureWriteAccess::class,
            'bind_account' => BindCurrentAccount::class,
            'route_permission' => EnsureRoutePermission::class,
            'api_user' => EnsureApiUser::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Ödeme sağlayıcılarının sunucu bildirimi ve 3D dönüşü (POST) CSRF dışıdır; sürücü imzayı doğrular.
        // CSP ihlal bildirimini tarayıcı kendisi gönderir (CSRF belirteci taşımaz).
        $middleware->validateCsrfTokens(except: ['odeme/bildirim/*', 'odeme/donus/*', 'csp-report']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AuthenticateSession::class,
            SetSecurityHeaders::class,
        ]);

        // Kurulmamış sistemde her istek kurulum sihirbazına gider.
        $middleware->prepend(EnsureInstalled::class);

        // Kurulum sihirbazı: veritabanına dokunan ara katmanlar (site erişimi,
        // oturum doğrulama) olmadan; oturum kurulum boyunca dosyada tutulur.
        $middleware->group('installer', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            HandleInertiaRequests::class,
            SetSecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // REST API her zaman JSON döner (istemci Accept başlığı göndermese de);
        // hata mesajları Türkçedir. Doğrulama hataları Laravel'in 422 biçimindedir.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(fn (Throwable $e, Request $request) => ApiExceptionRenderer::render($e, $request));
    })->create();

// Panelden kapatılan modüllerin paketleri auto-discovery'de atlanır (bkz. ModulePackageManifest).
// Bu bağlar, provider'lar kaydedilmeden (yapılandırma yüklendikten sonra) çözülür.
$app->singleton(ModuleState::class, fn (Application $app) => new ModuleState(
    (string) $app->make('config')->get('bayiyazilimi.module_state', $app->storagePath('app/modules.json')),
));

$app->singleton(PackageManifest::class, fn (Application $app) => new ModulePackageManifest(
    new Filesystem, $app->basePath(), $app->getCachedPackagesPath(), $app->make(ModuleState::class),
));
$app->alias(PackageManifest::class, ModulePackageManifest::class);

/*
 * Paylaşımlı hosting (DirectAdmin / cPanel): belge kökü değiştirilemiyorsa genel klasör `public/`
 * yerine `public_html` olur. Uygulama doğrudan alan adı klasöründeyse (alan/{app, vendor, …,
 * public_html}) ya da public_html'in yanındaki bir klasördeyse (alan/{uygulama, public_html})
 * derlenmiş dosyalar, `storage` bağlantısı ve index.php public_html'dedir.
 */
foreach ([dirname(__DIR__).'/public_html', dirname(__DIR__, 2).'/public_html'] as $sharedPublic) {
    if (is_file($sharedPublic.'/index.php')) {
        $app->usePublicPath($sharedPublic);

        break;
    }
}

return $app;
