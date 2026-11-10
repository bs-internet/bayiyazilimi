<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\InstalledModule;
use App\Models\Role;
use App\Models\User;
use App\Services\Panel\Settings\Modules\ModuleService;
use App\Support\Modules\ModuleCatalog;
use App\Support\Modules\ModuleDiscovery;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModulePackageManifest;
use App\Support\Modules\ModuleState;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bayi-modules-'.uniqid().'.json';
    $this->app->instance(ModuleState::class, new ModuleState($this->statePath));

    config([
        'bayiyazilimi.module_discovery_paths' => [base_path('tests/Fixtures/Modules/*/module.json')],
    ]);

    // Gerçek siteye istek gitmesin: sahte yanıt tanımlamayan testte istek
    // hata verir, katalog boş gelir.
    Http::preventStrayRequests();
    Cache::forget(ModuleCatalog::LAST_KEY);

    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    @unlink($this->statePath);
});

function moduleState(): ModuleState
{
    return new ModuleState(test()->statePath);
}

test('admin sees installed modules and the catalog with installed ones marked', function () {
    Http::fake([ModuleCatalog::URL => Http::response([
        ['key' => 'demo', 'name' => 'Demo', 'description' => 'Kurulu', 'url' => 'https://example.com/demo'],
        ['key' => 'pro', 'name' => 'Pro', 'description' => 'Kurulu olmayan ek modül.', 'url' => 'https://example.com/pro'],
    ])]);
    InstalledModule::query()->create(['key' => 'demo', 'name' => 'Demo', 'version' => '1.2.0', 'installed_at' => '2026-11-01 10:00:00']);

    $this->actingAs($this->admin)
        ->get(route('panel.settings.extensions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/settings/extensions/index')
            // Provider sınıfı olmayan modül kurulu sayılmaz; liste ada göre sıralı.
            ->has('modules', 2)
            ->where('modules.0.key', 'demo')
            ->where('modules.0.version', '1.2.0')
            ->where('modules.0.author', 'Test')
            ->where('modules.0.enabled', true)
            ->where('modules.0.required_by', ['Demo Eklenti'])
            ->where('modules.0.setup', 'done')
            ->where('modules.0.installed_at', fn (string $date) => str_starts_with($date, '2026-11-01'))
            ->where('modules.1.key', 'demo-addon')
            ->where('modules.1.requires', ['Demo'])
            // composer ile gelmiş ama bayi:install çalışmamış.
            ->where('modules.1.setup', 'pending')
            ->where('modules.1.installed_at', null)
            // Katalog uzaktan çekildiği için ertelenir.
            ->missing('catalog')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('catalog', 2)
                ->where('catalog.0.key', 'demo')
                ->where('catalog.0.installed', true)
                ->where('catalog.1.key', 'pro')
                ->where('catalog.1.installed', false)
                ->where('catalog.1.url', 'https://example.com/pro')));
});

test('a module whose new version was not installed yet is flagged', function () {
    InstalledModule::query()->create(['key' => 'demo', 'name' => 'Demo', 'version' => '1.1.0', 'installed_at' => now()]);

    $demo = collect(app(ModuleService::class)->installed())->firstWhere('key', 'demo');

    expect($demo['setup'])->toBe('update');
});

test('bayi:install records the module and bayi:uninstall removes it', function () {
    $this->artisan('bayi:install', ['module' => 'demo'])->assertSuccessful();

    $record = InstalledModule::query()->where('key', 'demo')->firstOrFail();
    expect($record->version)->toBe('1.2.0');

    // Yeniden kurulum sürümü günceller, ilk kurulum tarihini korur.
    $installedAt = $record->installed_at;
    $this->travel(2)->days();
    $record->forceFill(['version' => '1.0.0'])->save();

    $this->artisan('bayi:install', ['module' => 'demo'])->assertSuccessful();

    expect($record->fresh()->version)->toBe('1.2.0')
        ->and($record->fresh()->installed_at->equalTo($installedAt))->toBeTrue();

    $this->artisan('bayi:uninstall', ['module' => 'demo'])->assertSuccessful();

    expect(InstalledModule::query()->where('key', 'demo')->exists())->toBeFalse();
});

test('the catalog is fetched from bayiyazilimi.com on every visit', function () {
    Http::fake([ModuleCatalog::URL => Http::sequence()
        ->push([['key' => 'ptt', 'name' => 'PTT Kargo', 'description' => 'PTT ile gönderim.', 'url' => 'https://bayiyazilimi.com/ptt']])
        ->push(['modules' => [
            ['key' => 'ptt', 'name' => 'PTT Kargo', 'url' => 'https://bayiyazilimi.com/ptt'],
            ['key' => 'sms', 'name' => 'SMS', 'url' => 'javascript:alert(1)'],
            ['key' => 'demo', 'name' => 'Demo'],
            ['name' => 'Anahtarsız'],
        ]]),
    ]);

    expect(app(ModuleService::class)->catalog())->toBe([
        ['key' => 'ptt', 'name' => 'PTT Kargo', 'description' => 'PTT ile gönderim.', 'url' => 'https://bayiyazilimi.com/ptt', 'type' => 'extension', 'installed' => false],
    ]);

    // İkinci açılışta yeni liste; kurulu modül işaretlenir, anahtarsız girdi atlanır, http(s) dışı bağlantı düşer.
    expect(app(ModuleService::class)->catalog())->toBe([
        ['key' => 'ptt', 'name' => 'PTT Kargo', 'description' => null, 'url' => 'https://bayiyazilimi.com/ptt', 'type' => 'extension', 'installed' => false],
        ['key' => 'sms', 'name' => 'SMS', 'description' => null, 'url' => null, 'type' => 'extension', 'installed' => false],
        ['key' => 'demo', 'name' => 'Demo', 'description' => null, 'url' => null, 'type' => 'extension', 'installed' => true],
    ]);

    Http::assertSentCount(2);
});

test('the last fetched catalog is shown while bayiyazilimi.com is unreachable', function () {
    Http::fake([ModuleCatalog::URL => Http::sequence()
        ->push([['key' => 'ptt', 'name' => 'PTT Kargo', 'url' => 'https://bayiyazilimi.com/ptt']])
        ->push('', 500)]);

    app(ModuleService::class)->catalog();

    expect(app(ModuleService::class)->catalog())->toHaveCount(1)
        ->and(app(ModuleService::class)->catalog()[0]['key'] ?? null)->toBe('ptt');
});

test('the catalog is empty when bayiyazilimi.com was never reached', function () {
    Http::fake([ModuleCatalog::URL => Http::response('', 500)]);

    expect(app(ModuleService::class)->catalog())->toBe([]);
});

test('admin disables and re-enables a module, and both are logged', function () {
    $this->actingAs($this->admin)
        ->patch(route('panel.settings.extensions.update', 'demo-addon'), ['enabled' => false])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    expect(moduleState()->isEnabled('demo-addon'))->toBeFalse()
        ->and(moduleState()->disabled())->toBe(['demo-addon' => 'Tests\\Fixtures\\Modules\\DemoAddonServiceProvider'])
        ->and(Activity::query()->where('log_name', 'panel.module')->where('event', 'deactivated')->exists())->toBeTrue();

    $this->patch(route('panel.settings.extensions.update', 'demo-addon'), ['enabled' => true])
        ->assertSessionHas('toast.type', 'success');

    expect(moduleState()->isEnabled('demo-addon'))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'panel.module')->where('event', 'activated')->exists())->toBeTrue();
});

test('a module another enabled module needs cannot be disabled', function () {
    $this->actingAs($this->admin)
        ->patch(route('panel.settings.extensions.update', 'demo'), ['enabled' => false])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'warning');

    expect(moduleState()->isEnabled('demo'))->toBeTrue();

    // Bağımlı modül kapatılınca serbest kalır.
    $this->patch(route('panel.settings.extensions.update', 'demo-addon'), ['enabled' => false]);
    $this->patch(route('panel.settings.extensions.update', 'demo'), ['enabled' => false])
        ->assertSessionHas('toast.type', 'success');

    expect(moduleState()->isEnabled('demo'))->toBeFalse();
});

test('a module cannot be enabled while a module it needs is disabled', function () {
    moduleState()->disable(app(ModuleDiscovery::class)->find('demo-addon'));
    moduleState()->disable(app(ModuleDiscovery::class)->find('demo'));

    $this->actingAs($this->admin)
        ->patch(route('panel.settings.extensions.update', 'demo-addon'), ['enabled' => true])
        ->assertSessionHas('toast.type', 'warning');

    expect(moduleState()->isEnabled('demo-addon'))->toBeFalse();
});

test('unknown or not installed modules are not found', function (string $key) {
    $this->actingAs($this->admin)
        ->patch(route('panel.settings.extensions.update', $key), ['enabled' => false])
        ->assertNotFound();
})->with(['yok', 'not-installed']);

test('the enabled flag is required', function () {
    $this->actingAs($this->admin)
        ->patch(route('panel.settings.extensions.update', 'demo'), [])
        ->assertSessionHasErrors('enabled');
});

test('the modules screen needs its permission', function () {
    // Admin görevi kurulumda tüm panel yetkilerini alır; yetkisiz bir görevle denenir.
    Role::query()->create(['name' => 'Yetkisiz Görev', 'guard_name' => 'web']);
    $admin = User::factory()->state(['user_type' => UserType::Admin])->withRole('Yetkisiz Görev')->create();

    $this->actingAs($admin)
        ->get(route('panel.settings.extensions.index'))
        ->assertForbidden();
});

test('package discovery skips the packages of disabled modules', function () {
    $cache = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bayi-packages-'.uniqid().'.php';
    file_put_contents($cache, '<?php return '.var_export([
        'vendor/core' => ['providers' => ['Core\\Provider']],
        'bsinternet/demo' => ['providers' => ['Demo\\Provider', 'Demo\\ExtraProvider'], 'aliases' => ['Demo' => 'Demo\\Facade']],
    ], true).';');

    $state = moduleState();
    $manifest = new ModulePackageManifest(new Filesystem, base_path(), $cache, $state);

    expect($manifest->providers())->toBe(['Core\\Provider', 'Demo\\Provider', 'Demo\\ExtraProvider'])
        ->and($manifest->aliases())->toBe(['Demo' => 'Demo\\Facade']);

    $state->disable(new ModuleManifest(key: 'demo', name: 'Demo', description: null, version: '1.0.0', provider: 'Demo\\Provider'));
    $manifest = new ModulePackageManifest(new Filesystem, base_path(), $cache, moduleState());

    expect($manifest->providers())->toBe(['Core\\Provider'])
        ->and($manifest->aliases())->toBe([]);

    @unlink($cache);
});
