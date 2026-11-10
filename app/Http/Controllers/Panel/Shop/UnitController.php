<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveUnitRequest;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar → Birimler. Ürünler birimi adıyla tuttuğundan ad değişince
 * o birimdeki ürünler de güncellenir; kullanılan birim silinemez.
 */
class UnitController extends Controller
{
    public function index(): Response
    {
        $usage = Product::withTrashed()->selectRaw('unit, count(*) as total')->groupBy('unit')->pluck('total', 'unit');

        return Inertia::render('panel/tools/definitions/units/index', [
            'units' => Unit::query()->ordered()->orderBy('name')->get()->map(fn (Unit $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'status' => $u->status,
                'sort_order' => $u->sort_order,
                'products_count' => (int) ($usage[$u->name] ?? 0),
            ])->all(),
        ]);
    }

    public function store(SaveUnitRequest $request): RedirectResponse
    {
        $unit = Unit::query()->create([
            'name' => $request->validated('name'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'status' => Status::Active,
        ]);

        CatalogChangedEvent::dispatch($unit, 'unit', 'created', __(':name birimini ekledi.', ['name' => $unit->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name birimi eklendi.', ['name' => $unit->name])]);
    }

    public function update(SaveUnitRequest $request, Unit $unit): RedirectResponse
    {
        $old = $unit->name;

        DB::transaction(function () use ($request, $unit, $old) {
            $unit->update([
                'name' => $request->validated('name'),
                'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            ]);

            if ($unit->name !== $old) {
                Product::withTrashed()->where('unit', $old)->update(['unit' => $unit->name]);
            }
        });

        CatalogChangedEvent::dispatch($unit, 'unit', 'updated', __(':name birimini güncelledi.', ['name' => $unit->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name birimi güncellendi.', ['name' => $unit->name])]);
    }

    public function status(Request $request, Unit $unit): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($unit->hasStatus($request->input('status'))) {
            return back();
        }

        $unit->update(['status' => $unit->isActive() ? Status::Passive : Status::Active]);

        CatalogChangedEvent::dispatch($unit, 'unit', 'status', $unit->isActive()
            ? __(':name birimini aktifleştirdi.', ['name' => $unit->name])
            : __(':name birimini pasifleştirdi.', ['name' => $unit->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => $unit->isActive() ? __(':name birimi aktifleştirildi.', ['name' => $unit->name]) : __(':name birimi pasifleştirildi.', ['name' => $unit->name])]);
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        if (Product::withTrashed()->where('unit', $unit->name)->exists()) {
            throw new ShopException(__(':name birimi ürünlerde kullanılıyor; silmek yerine pasifleştirin.', ['name' => $unit->name]));
        }

        $unit->delete();

        CatalogChangedEvent::dispatch(null, 'unit', 'deleted', __(':name birimini sildi.', ['name' => $unit->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name birimi silindi.', ['name' => $unit->name])]);
    }
}
