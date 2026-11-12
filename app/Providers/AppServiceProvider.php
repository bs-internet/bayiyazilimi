<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\Setting;
use App\Support\Install\EnvironmentFile;
use App\Support\Install\InstallState;
use App\Support\Permissions\PermissionDependencies;
use App\Support\Security\TrustedProxies;
use App\Support\Themes\ThemeManager;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Sanctum\Sanctum;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Yetki bağımlılık haritası istek başına bir kez kurulur.
        $this->app->scoped(PermissionDependencies::class);

        // Etkin tema istek başına bir kez çözülür.
        $this->app->scoped(ThemeManager::class);

        $this->app->singleton(InstallState::class, fn () => new InstallState($this->app->make(EnvironmentFile::class)));
        $this->app->singleton(EnvironmentFile::class, fn () => new EnvironmentFile(
            $this->app->basePath('.env'),
            $this->app->basePath('.env.example'),
        ));

        $this->prepareForInstallation();
    }

    /**
     * Kurulmamış sistem: veritabanı henüz yok. Oturum / önbellek / kuyruk
     * dosyaya ve senkrona alınır ki kurulum sihirbazı çalışabilsin; uygulama
     * anahtarı yoksa üretilip `.env`'e yazılır (çerez şifrelemesi için).
     */
    private function prepareForInstallation(): void
    {
        if ($this->app->runningUnitTests() || $this->app->make(InstallState::class)->installed()) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        if ((string) config('app.key') === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));

            try {
                $this->app->make(EnvironmentFile::class)->set(['APP_KEY' => $key]);
            } catch (Throwable) {
                // .env yazılamıyor: gereksinim ekranı bunu gösterir; anahtar bu istek için geçerli.
            }

            config(['app.key' => $key]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureSystemDefaults();
        $this->configureUserPreferences();
        $this->configureThemes();
        $this->configureApi();
    }

    /**
     * Sunucu proxy / CDN arkasındaysa gerçek istemci IP'si ve https bilgisi yalnız
     * `.env`'de belirtilen proxy'lerden kabul edilir (bkz. config/bayiyazilimi.php).
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = TrustedProxies::resolve((string) config('bayiyazilimi.trusted_proxies'));

        if ($proxies !== null) {
            TrustProxies::at($proxies);
        }
    }

    /**
     * REST API: UUID anahtarlı Sanctum modeli ve anahtar başına dakikalık istek sınırı.
     */
    protected function configureApi(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('api', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();
            $key = $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'ip:'.$request->ip();

            return Limit::perMinute((int) config('bayiyazilimi.api_rate_limit', 120))->by($key);
        });
    }

    /**
     * Mağaza sayfası (`shop/*`) etkin temada varsa temanınki açılır (bkz. ThemeManager).
     */
    protected function configureThemes(): void
    {
        Inertia::transformComponentUsing(
            fn (mixed $component): mixed => is_string($component)
                ? $this->app->make(ThemeManager::class)->component($component)
                : $component,
        );
    }

    /**
     * Super Admin rolü TÜM yetkilere otomatik sahip — Spatie permission listesine
     * eklenmesine gerek yok. Diğer roller yalnızca açıkça atanmış izinlerle çalışır.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Üretim dışında N+1'i erken yakala: lazy loading'i yasakla.
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Genel Ayarlar → varsayılan dil ve zaman dilimi uygulamanın varsayılanı olur
     * (kullanıcı tercihi yoksa bunlar geçerlidir). Ayarlar tablosu henüz yoksa
     * (kurulum, migrate) config değerleri kalır.
     */
    protected function configureSystemDefaults(): void
    {
        try {
            $locale = Setting::get('default_language_code');
            $timezone = Setting::get('default_timezone');
        } catch (Throwable) {
            return;
        }

        if (is_string($locale) && $locale !== '') {
            config(['app.locale' => $locale]);
            app()->setLocale($locale);
        }

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }

    protected function configureUserPreferences(): void
    {
        // View render edilmeden önce (Inertia dahil) ayarları uygula
        view()->composer('*', function () {
            if (Auth::check()) {
                // Session'dan al, yoksa config'deki varsayılanı kullan
                $locale = session('locale', config('app.locale'));
                $timezone = session('timezone', config('app.timezone'));

                // Sistem ayarlarını çalışma zamanında güncelle
                app()->setLocale($locale);
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);

                // Carbon için immutable desteğini ve yerelleştirmeyi koru[cite: 2]
                Carbon::setLocale($locale);
            }
        });
    }
}
