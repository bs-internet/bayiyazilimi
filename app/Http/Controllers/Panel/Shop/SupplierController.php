<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar → Tedarikçiler. Mal kabulde seçilir; mal kabulü olan tedarikçi
 * silinemez (pasifleştirilir).
 */
class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('panel/tools/definitions/suppliers/index', [
            'suppliers' => Supplier::query()->withCount('receipts')->orderBy('name')->get()->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'tax_office' => $s->tax_office,
                'tax_number' => $s->tax_number,
                'phone' => $s->phone,
                'email' => $s->email,
                'address' => $s->address,
                'note' => $s->note,
                'status' => $s->status,
                'receipts_count' => $s->receipts_count,
            ])->all(),
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create([...$request->validated(), 'status' => Status::Active]);

        CatalogChangedEvent::dispatch($supplier, 'supplier', 'created', __(':name tedarikçisini ekledi.', ['name' => $supplier->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name eklendi.', ['name' => $supplier->name])]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        CatalogChangedEvent::dispatch($supplier, 'supplier', 'updated', __(':name tedarikçisini güncelledi.', ['name' => $supplier->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name güncellendi.', ['name' => $supplier->name])]);
    }

    public function status(Request $request, Supplier $supplier): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($supplier->hasStatus($request->input('status'))) {
            return back();
        }

        $supplier->update(['status' => $supplier->isActive() ? Status::Passive : Status::Active]);

        CatalogChangedEvent::dispatch($supplier, 'supplier', 'status', $supplier->isActive()
            ? __(':name tedarikçisini aktifleştirdi.', ['name' => $supplier->name])
            : __(':name tedarikçisini pasifleştirdi.', ['name' => $supplier->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => $supplier->isActive() ? __(':name aktifleştirildi.', ['name' => $supplier->name]) : __(':name pasifleştirildi.', ['name' => $supplier->name])]);
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        if ($supplier->receipts()->exists()) {
            throw new ShopException(__(':name mal kabullerde kullanılıyor; silmek yerine pasifleştirin.', ['name' => $supplier->name]));
        }

        $supplier->delete();

        CatalogChangedEvent::dispatch(null, 'supplier', 'deleted', __(':name tedarikçisini sildi.', ['name' => $supplier->name]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name silindi.', ['name' => $supplier->name])]);
    }
}
