<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\System\UpdateService;
use App\Support\BayiYazilimi;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
    Cache::flush();
});

test('a fresh install records the code version, so nothing is pending', function () {
    expect(app(UpdateService::class)->installedVersion())->toBe(BayiYazilimi::VERSION)
        ->and(app(UpdateService::class)->pending())->toBeFalse();

    $this->actingAs($this->admin)->get(route('panel.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('systemUpdate', false));
});

test('after new files the super admin sees the update banner and completes it from the panel', function () {
    Setting::query()->where('key', UpdateService::VERSION_SETTING)->update(['value' => '0.9']);
    Setting::flushCache();

    $this->actingAs($this->admin)->get(route('panel.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('systemUpdate', true));

    // Diğer yöneticiler uyarıyı görmez.
    $other = User::factory()->admin()->create();
    $other->syncRoles(['Admin']);
    $this->flushSession();
    $this->actingAs($other)->get(route('panel.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('systemUpdate', false));

    $this->flushSession();
    $this->actingAs($this->admin)->post(route('panel.system.update'))
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    expect(app(UpdateService::class)->installedVersion())->toBe(BayiYazilimi::VERSION)
        ->and(Activity::query()->where('log_name', 'panel.system')->exists())->toBeTrue();
});

test('bayi:update completes the update from the command line', function () {
    Setting::query()->where('key', UpdateService::VERSION_SETTING)->update(['value' => '0.9']);
    Setting::flushCache();

    $this->artisan('bayi:update', ['--no-backup' => true, '--no-maintenance' => true])
        ->expectsOutputToContain('Veritabanı güncellendi.')
        ->assertSuccessful();

    expect(app(UpdateService::class)->installedVersion())->toBe(BayiYazilimi::VERSION);
});
