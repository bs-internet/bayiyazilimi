<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\SiteAccessGate;
use App\Http\Responses\Auth\PasswordResetLinkFailedResponse;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkFailedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureRouteThrottles();
        $this->configureLoginTracking();
    }

    /**
     * Fortify yalnız giriş ve iki adımlı doğrulama sınırlayıcılarını kendi bağlar;
     * şifre sıfırlama ve kayıt rotalarına sınırlayıcı burada eklenir.
     */
    private function configureRouteThrottles(): void
    {
        $this->app->booted(function (): void {
            if ($this->app->routesAreCached()) {
                return;
            }

            $routes = $this->app['router']->getRoutes();
            $routes->refreshNameLookups();

            foreach (['password.email' => 'password-email', 'password.update' => 'password-update', 'register.store' => 'register'] as $route => $limiter) {
                $routes->getByName($route)?->middleware('throttle:'.$limiter);
            }
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Girişi kapalı (pasif) ya da panele giremeyen türdeki kullanıcı oturum açamaz:
        // aksi halde giriş tamamlanır, son giriş / giriş günlüğü / "yeni cihaz" e-postası
        // üretilir ve kullanıcı ancak sonraki istekte çıkarılırdı.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where(Fortify::username(), $request->input(Fortify::username()))->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
                return null;
            }

            if (! $user->status->canLogin() || (! $user->user_type->isAdmin() && ! $user->user_type->isMember())) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Hesabınız giriş için kapalı; yöneticinizle iletişime geçin.'),
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        // Oturum sayfalarındaki "Mağazaya dön" bağlantısı: vitrin modülü kuruluysa vitrin adresi; yoksa gizli.
        $shop = fn (): array => ['storefrontUrl' => hooks()->apply('shop.storefront_url', null)];

        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            // Kayıt yalnız site herkese açıksa (vitrin modülü) vardır; B2B'de bayiyi panel ekler.
            'canRegister' => Features::enabled(Features::registration()) && SiteAccessGate::isPublic(),
            'status' => $request->session()->get('status'),
            ...$shop(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            ...$shop(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            ...$shop(),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
            ...$shop(),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            // Üyelik formunda onaylatılan sözleşmeler (Tanımlamalar → Sözleşmeler).
            'contracts' => Contract::forForm(Contract::REGISTRATION),
            ...$shop(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge', $shop()));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password', $shop()));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Spam kayıt koruması: IP başına dakikada 5 deneme.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Şifre sıfırlama maili spam'i ve adres taraması koruması: e-posta + IP
        // başına dakikada 3, IP başına (farklı adresler denense de) dakikada 20 deneme.
        RateLimiter::for('password-email', function (Request $request) {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return [Limit::perMinute(3)->by($key), Limit::perMinute(20)->by('ip:'.$request->ip())];
        });

        // Şifre sıfırlama tamamlama: IP başına dakikada 3 deneme.
        RateLimiter::for('password-update', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }

    /**
     * Configure login tracking and session preferences.
     */
    private function configureLoginTracking(): void
    {
        Event::listen(Login::class, function (Login $event) {
            /** @var User $user */
            $user = $event->user;

            // Son giriş tarihini güncelle
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();

            // loadMissing ile dil ilişkisini verimli çekiyoruz
            $user->loadMissing('language');

            session([
                'locale' => $user->language?->code ?? $user->getAttribute('locale') ?? config('app.locale'),
                'timezone' => $user->timezone ?? config('app.timezone'),
            ]);
        });
    }
}
