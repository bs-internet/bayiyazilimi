<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModulePackageManifest;
use App\Support\Modules\ModuleState;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * `modules` paylaşılan prop'u: kurulu ve açık modül paketleri. Ön yüz kapalı
 * modüllerin derlemede kalan yuva kayıtlarını bununla gizler. Modülsüz
 * çekirdekte liste boştur.
 */
it('shares only the enabled module packages', function () {
    $admin = User::factory()->admin()->create();
    $manifest = app(ModulePackageManifest::class);
    $active = $manifest->activeModulePackages(['bsinternet']);

    $this->actingAs($admin)->get(route('panel.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('modules', $active));

    if ($active === []) {
        return;
    }

    // Bir modül kapatılınca paketi listeden düşer.
    $package = $active[0];
    $provider = (string) ($manifest->manifest[$package]['providers'][0] ?? '');
    app(ModuleState::class)->disable(ModuleManifest::fromArray(['key' => 'test-module', 'name' => 'Test', 'version' => '1.0.0', 'provider' => $provider]));

    try {
        expect($manifest->activeModulePackages(['bsinternet']))->not->toContain($package);
    } finally {
        app(ModuleState::class)->enable('test-module');
    }
});
