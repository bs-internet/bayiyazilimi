<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\Modules\ModuleCatalog;
use App\Support\Modules\ModuleState;
use App\Support\Themes\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bayi-themes-'.uniqid().'.json';
    $this->app->instance(ModuleState::class, new ModuleState($this->statePath));

    config([
        'bayiyazilimi.module_discovery_paths' => [
            base_path('tests/Fixtures/Modules/*/module.json'),
            base_path('tests/Fixtures/Themes/*/module.json'),
        ],
    ]);
    // Tema paketinin provider'ı bunu yapar; testte provider yüklenmez.
    config()->push('inertia.pages.paths', base_path('tests/Fixtures/Themes/ornek/resources/js/pages'));

    Http::preventStrayRequests();
    Cache::forget(ModuleCatalog::LAST_KEY);

    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    @unlink($this->statePath);
});

function themes(): ThemeManager
{
    app()->forgetScopedInstances();

    return app(ThemeManager::class);
}

test('the themes screen lists installed themes and only themes from the catalog', function () {
    Http::fake([ModuleCatalog::URL => Http::response([
        ['key' => 'demo', 'name' => 'Demo', 'url' => 'https://example.com/demo'],
        ['key' => 'ornek', 'name' => 'Örnek Tema', 'type' => 'theme', 'url' => 'https://example.com/ornek'],
        ['key' => 'modern', 'name' => 'Modern', 'type' => 'theme', 'url' => 'https://example.com/modern'],
    ])]);

    $this->actingAs($this->admin)
        ->get(route('panel.settings.themes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/settings/extensions/themes')
            ->has('themes', 1)
            ->where('themes.0.key', 'ornek')
            ->where('active', null)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('catalog', 2)
                ->where('catalog.0.key', 'ornek')
                ->where('catalog.0.installed', true)
                ->where('catalog.1.installed', false)));

    // Eklentiler ekranı temaları listelemez.
    $this->get(route('panel.settings.extensions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules', fn ($modules) => collect($modules)->pluck('key')->doesntContain('ornek'))
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('catalog', fn ($catalog) => collect($catalog)->pluck('key')->all() === ['demo'])));
});

test('activating a theme swaps only the store pages it provides', function () {
    expect(themes()->component('shop/home'))->toBe('shop/home');

    $this->actingAs($this->admin)
        ->put(route('panel.settings.themes.update'), ['theme' => 'ornek'])
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Mağaza teması: Örnek Tema.');

    expect(Setting::get(ThemeManager::SETTING))->toBe('ornek')
        ->and(themes()->component('shop/home'))->toBe('themes/ornek/shop/home')
        // Temanın vermediği sayfa: varsayılan.
        ->and(themes()->component('shop/cart'))->toBe('shop/cart')
        // Giriş ekranı ve paneller temadan etkilenmez.
        ->and(themes()->component('auth/login'))->toBe('auth/login')
        ->and(Activity::query()->where('log_name', 'panel.theme')->where('properties->theme', 'ornek')->exists())->toBeTrue();

    // Inertia yanıtının bileşen adı da dönüşür.
    Route::middleware('web')->get('/__tema-deneme', fn () => Inertia::render('shop/home'));
    app()->forgetScopedInstances();
    $this->get('/__tema-deneme')->assertInertia(fn (Assert $page) => $page->component('themes/ornek/shop/home'));

    $this->put(route('panel.settings.themes.update'), ['theme' => null])->assertRedirect();
    expect(themes()->active())->toBeNull()
        ->and(themes()->component('shop/home'))->toBe('shop/home');
});

test('an unknown theme is refused and a disabled theme falls back to the default', function () {
    $this->actingAs($this->admin)
        ->put(route('panel.settings.themes.update'), ['theme' => 'yok'])
        ->assertNotFound();

    $this->put(route('panel.settings.themes.update'), ['theme' => 'ornek']);
    app(ModuleState::class)->update(fn (array $disabled): array => [...$disabled, 'ornek' => 'Tests\Fixtures\Themes\OrnekThemeServiceProvider']);

    expect(themes()->active())->toBeNull()
        ->and(themes()->component('shop/home'))->toBe('shop/home');
});
