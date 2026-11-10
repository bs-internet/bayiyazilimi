<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveBrandRequest;
use App\Http\Resources\Panel\Shop\BrandResource;
use App\Models\Brand;
use App\Services\Panel\Shop\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $brands = Brand::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('panel/shop/brands/index', [
            'brands' => BrandResource::collection($brands)->resolve($request),
        ]);
    }

    public function store(SaveBrandRequest $request, CatalogService $catalog): RedirectResponse
    {
        $brand = $catalog->saveBrand(null, $request->payload(), $request->file('logo'), false, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name markası oluşturuldu.', ['name' => $brand->name])]);
    }

    public function update(SaveBrandRequest $request, Brand $brand, CatalogService $catalog): RedirectResponse
    {
        $catalog->saveBrand($brand, $request->payload(), $request->file('logo'), $request->boolean('remove_logo'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name markası güncellendi.', ['name' => $brand->name])]);
    }

    public function status(Request $request, Brand $brand, CatalogService $catalog): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($brand->hasStatus($request->input('status'))) {
            return back();
        }

        $catalog->toggleStatus($brand, 'brand', __(':name marka', ['name' => $brand->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Marka durumu güncellendi.')]);
    }

    public function destroy(Request $request, Brand $brand, CatalogService $catalog): RedirectResponse
    {
        $catalog->deleteBrand($brand, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name markası silindi.', ['name' => $brand->name])]);
    }
}
