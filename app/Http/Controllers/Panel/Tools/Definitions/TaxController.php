<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Tools\Definitions;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Tools\Definitions\SaveTaxRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Tools\Definitions\TaxResource;
use App\Models\Setting;
use App\Models\Tax;
use App\Services\Panel\Tools\Definitions\TaxService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxController extends Controller
{
    public function index(Request $request): Response
    {
        $taxes = Tax::query()
            ->orderBy('name')
            ->paginate(Pagination::PER_PAGE);

        return Inertia::render('panel/tools/definitions/tax/index', [
            'taxes' => PaginatedResource::make($taxes, TaxResource::class, $request),
            'defaults' => [
                'default_tax_id' => Setting::get('default_tax_id'),
            ],
        ]);
    }

    public function deleted(Request $request): Response
    {
        $taxes = Tax::query()
            ->onlyTrashed()
            ->orderBy('name')
            ->paginate(Pagination::PER_PAGE);

        return Inertia::render('panel/tools/definitions/tax/deleted', [
            'taxes' => PaginatedResource::make($taxes, TaxResource::class, $request),
        ]);
    }

    public function store(SaveTaxRequest $request, TaxService $service): RedirectResponse
    {
        $tax = $service->create($request->validated(), $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':name vergi oranı oluşturuldu.', ['name' => $tax->name])]);
    }

    public function update(SaveTaxRequest $request, Tax $tax, TaxService $service): RedirectResponse
    {
        $service->update($tax, $request->validated(), $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':name vergi oranı güncellendi.', ['name' => $tax->name])]);
    }

    public function destroy(Request $request, Tax $tax, TaxService $service): RedirectResponse
    {
        $name = $tax->name;

        $service->delete($tax, $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':name vergi oranı silindi.', ['name' => $name])]);
    }

    public function status(Request $request, Tax $tax, TaxService $service): RedirectResponse
    {
        $wasActive = $tax->status === Status::Active;
        $wasActive
            ? $service->deactivate($tax, $request->user())
            : $service->activate($tax, $request->user());

        $action = $wasActive ? __('pasifleştirildi') : __('aktifleştirildi');

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':name vergi oranı :action.', ['name' => $tax->name, 'action' => $action])]);
    }

    public function restore(Request $request, string $tax, TaxService $service): RedirectResponse
    {
        $model = Tax::onlyTrashed()->findOrFail($tax);

        $service->restore($model, $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':name vergi oranı geri alındı.', ['name' => $model->name])]);
    }

    public function forceDelete(Request $request, string $tax, TaxService $service): RedirectResponse
    {
        $model = Tax::onlyTrashed()->findOrFail($tax);

        $name = $model->name;

        $service->forceDelete($model, $request->user());

        return back()
            ->with('toast', ['type' => 'success', 'message' => __(':name vergi oranı tamamen silindi.', ['name' => $name])]);
    }
}
