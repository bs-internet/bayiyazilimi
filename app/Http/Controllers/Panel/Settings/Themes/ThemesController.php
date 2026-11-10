<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Settings\Themes;

use App\Events\Panel\Settings\Themes\ThemeActivatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Settings\Themes\ActivateThemeRequest;
use App\Services\Panel\Settings\Modules\ModuleService;
use App\Support\Modules\ModuleManifest;
use App\Support\Themes\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ayarlar → Eklentiler → Temalar: kurulu mağaza temaları ve etkin tema seçimi.
 */
class ThemesController extends Controller
{
    public function index(ThemeManager $themes, ModuleService $modules): Response
    {
        return Inertia::render('panel/settings/extensions/themes', [
            'themes' => array_values(array_map(fn (ModuleManifest $theme): array => [
                'key' => $theme->key,
                'name' => $theme->name,
                'description' => $theme->description,
                'version' => $theme->version,
                'author' => isset($theme->author['name']) ? (string) $theme->author['name'] : null,
            ], $themes->installed())),
            'active' => $themes->active()?->key,
            // Uzaktan çekilir; sayfa açılışını bekletmesin diye ertelenir.
            'catalog' => Inertia::defer(fn () => $modules->catalog(ModuleManifest::TYPE_THEME)),
        ]);
    }

    public function update(ActivateThemeRequest $request, ThemeManager $themes): RedirectResponse
    {
        $key = $request->validated('theme');
        $theme = $key === null || $key === '' ? null : $themes->find((string) $key);

        abort_if($key !== null && $key !== '' && $theme === null, 404);

        if ($themes->active()?->key !== $theme?->key) {
            $themes->activate($theme);
            event(new ThemeActivatedEvent($theme?->key, $theme->name ?? __('Varsayılan tema'), $request->user()));
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Mağaza teması: :name.', ['name' => $theme->name ?? __('Varsayılan tema')]),
        ]);
    }
}
