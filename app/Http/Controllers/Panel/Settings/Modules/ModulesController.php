<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Settings\Modules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Settings\Modules\UpdateModuleStatusRequest;
use App\Services\Panel\Settings\Modules\ModuleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModulesController extends Controller
{
    public function index(ModuleService $modules): Response
    {
        return Inertia::render('panel/settings/extensions/index', [
            'modules' => $modules->installed(),
            // Uzaktan çekilir; sayfa açılışını bekletmesin diye ertelenir.
            'catalog' => Inertia::defer(fn () => $modules->catalog()),
        ]);
    }

    public function update(UpdateModuleStatusRequest $request, string $module, ModuleService $modules): RedirectResponse
    {
        $manifest = $modules->find($module);

        abort_if($manifest === null, 404);

        $enabled = $request->boolean('enabled');
        $modules->setEnabled($manifest, $enabled, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $enabled
                ? __(':name eklentisi açıldı.', ['name' => $manifest->name])
                : __(':name eklentisi kapatıldı. Verileri silinmedi; yeniden açtığınızda kaldığı yerden devam eder.', ['name' => $manifest->name]),
        ]);
    }
}
