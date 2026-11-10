<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\System;

use App\Events\Panel\System\SystemUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Services\System\UpdateService;
use App\Support\BayiYazilimi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Panelden güncellemeyi tamamlama (Super Admin): sunucuda terminal yoksa
 * (paylaşımlı hosting) yeni dosyalar yüklendikten sonra veritabanı ve eklenti
 * kurulum adımları buradan çalıştırılır. `bayi:update` ile aynı servis.
 */
class UpdateController extends Controller
{
    public function store(Request $request, UpdateService $update): RedirectResponse
    {
        @set_time_limit(0);

        try {
            $steps = $update->run(fn (string $message) => null);
        } catch (Throwable $e) {
            report($e);

            return back()->with('toast', ['type' => 'error', 'message' => __('Güncelleme tamamlanamadı: :error', ['error' => $e->getMessage()])]);
        }

        event(new SystemUpdatedEvent(BayiYazilimi::VERSION, $steps, $request->user()));

        return back()->with('toast', ['type' => 'success', 'message' => __('Sistem :version sürümüne güncellendi.', ['version' => BayiYazilimi::VERSION])]);
    }
}
