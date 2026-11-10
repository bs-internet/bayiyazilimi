<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Tools\Cache;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Tools\Cache\ClearCacheRequest;
use App\Services\Panel\Tools\Cache\CacheService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CacheController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('panel/tools/cache/index');
    }

    public function clear(ClearCacheRequest $request, CacheService $service): RedirectResponse
    {
        $type = $request->validated('type');
        $user = $request->user();

        if ($type === 'all') {
            $service->clearAll($user);
            $message = __('Tüm önbellek temizlendi.');
        } else {
            $service->clear($type, $user);
            $message = match ($type) {
                'application' => __('Uygulama önbelleği temizlendi.'),
                'config' => __('Yapılandırma önbelleği temizlendi.'),
                'route' => __('Rota önbelleği temizlendi.'),
                'view' => __('Görünüm önbelleği temizlendi.'),
                'event' => __('Olay önbelleği temizlendi.'),
                'compiled' => __('Derlenmiş önbellek temizlendi.'),
            };
        }

        return back()
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
