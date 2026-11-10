<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Services\System\UpdateService;
use App\Support\Branding;
use App\Support\Modules\ModulePackageManifest;
use App\Support\Registry\MenuRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $authUserPayload = null;
        if ($user !== null) {
            $authUserPayload = $user->toArray();
            $authUserPayload['roles'] = $user->getRoleNames()->all();
            $authUserPayload['permissions'] = $user->getAllPermissions()->pluck('name')->all();
        }

        return [
            ...parent::share($request),
            'name' => Branding::name(),
            'branding' => Branding::toArray(),
            'auth' => [
                'type' => $user?->user_type === UserType::Admin ? 'panel' : 'app',
                'user' => $authUserPayload,
            ],
            // Modül-farkında sidebar menüsü: MenuRegistry'den kullanıcıya göre
            // (alan + yetki) kurulur. Mevcut auth/branding/flash sözleşmesine ektir.
            'navigation' => fn (): array => $user === null
                ? []
                : app(MenuRegistry::class)->forUser($user),
            'flash' => [
                'toast' => $request->session()->get('toast'),
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            // Açık modül paketleri: kapalı modülün derlemede kalan ön yüz parçaları gizlenir.
            'modules' => fn (): array => app(ModulePackageManifest::class)
                ->activeModulePackages(array_values((array) config('bayiyazilimi.module_vendors', []))),
            // Kod yeni sürümdeyken veritabanı / eklenti kurulum adımları çalışmamışsa
            // Super Admin'e panelde "güncellemeyi tamamla" uyarısı (UpdateService).
            'systemUpdate' => fn (): bool => $user !== null
                && $user->user_type === UserType::Admin
                && $user->hasRole('Super Admin')
                && app(UpdateService::class)->pending(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
