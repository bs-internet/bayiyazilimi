<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shop\ProductExporter;
use App\Services\Shop\ProductImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('panel/shop/import/index', [
            'result' => $request->session()->get('import_result'),
            'productCount' => Product::query()->count(),
        ]);
    }

    /**
     * Boş ürün şablonu (mevcut depoların stok sütunları + Açıklamalar sayfası).
     */
    public function template(ProductExporter $exporter): BinaryFileResponse
    {
        return response()->download($exporter->template(), 'urun-sablonu.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function store(Request $request, ProductImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:51200'],
            'update_existing' => ['boolean'],
            'update_stock' => ['boolean'],
            'download_images' => ['boolean'],
        ]);

        @set_time_limit(600);

        $path = $request->file('file')->store('imports', 'local');

        try {
            $result = $importer->import(Storage::disk('local')->path($path), [
                'images_dir' => null,
                'update_existing' => $request->boolean('update_existing'),
                'update_stock' => $request->boolean('update_stock'),
                'download_images' => $request->boolean('download_images'),
                'causer' => $request->user(),
            ]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return back()
            ->with('import_result', $result)
            ->with('toast', [
                'type' => ($result['errors'] ?? []) === [] ? 'success' : 'warning',
                'message' => __('İçe aktarma tamamlandı: :created yeni, :updated güncellendi, :skipped atlandı.', ['created' => $result['created'], 'updated' => $result['updated'], 'skipped' => $result['skipped']]),
            ]);
    }
}
