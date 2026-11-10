<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SiteAccessGate;
use App\Http\Requests\Panel\Shop\SaveContractRequest;
use App\Models\Contract;
use App\Services\Panel\Shop\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tanımlamalar → Sözleşmeler: ödeme / üyelik formunda onaylatılan ve bayi
 * panelinde okunan metinler. Yeni sözleşme pasif başlar; durum
 * listedeki anahtardan değişir.
 */
class ContractController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('panel/tools/definitions/contracts/index', [
            'contracts' => Contract::query()->ordered()->orderBy('title')->get()->map(fn (Contract $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'show_on_checkout' => $c->show_on_checkout,
                'show_on_registration' => $c->show_on_registration,
                'status' => $c->status->value,
                'sort_order' => $c->sort_order,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])->values()->all(),
            'registration' => self::registrationOpen(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/tools/definitions/contracts/edit', ['contract' => null, 'registration' => self::registrationOpen()]);
    }

    public function store(SaveContractRequest $request, CatalogService $catalog): RedirectResponse
    {
        $contract = $catalog->saveContract(null, [...$request->payload(), 'status' => Status::Passive], $request->user());

        return to_route('panel.shop.contracts.edit', $contract->id)
            ->with('toast', ['type' => 'success', 'message' => __(':title oluşturuldu. Yayınlamak için listeden aktifleştirin.', ['title' => $contract->title])]);
    }

    public function edit(Contract $contract): Response
    {
        return Inertia::render('panel/tools/definitions/contracts/edit', [
            'contract' => [
                'id' => $contract->id,
                'title' => $contract->title,
                'slug' => $contract->slug,
                'content' => $contract->content,
                'show_on_checkout' => $contract->show_on_checkout,
                'show_on_registration' => $contract->show_on_registration,
                'status' => $contract->status->value,
                'sort_order' => $contract->sort_order,
                'updated_at' => $contract->updated_at?->toIso8601String(),
            ],
            'registration' => self::registrationOpen(),
        ]);
    }

    /**
     * Üyelik formu yalnız site herkese açıkken (vitrin modülü) vardır; B2B
     * çekirdekte bayiyi yönetici açar, "Üyelik formunda göster" gizlenir.
     */
    private static function registrationOpen(): bool
    {
        return SiteAccessGate::isPublic();
    }

    public function update(SaveContractRequest $request, Contract $contract, CatalogService $catalog): RedirectResponse
    {
        $catalog->saveContract($contract, $request->payload(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':title güncellendi.', ['title' => $contract->title])]);
    }

    public function status(Request $request, Contract $contract): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($contract->hasStatus($request->input('status'))) {
            return back();
        }

        $contract->update(['status' => $contract->isActive() ? Status::Passive : Status::Active]);

        CatalogChangedEvent::dispatch($contract, 'contract', 'status', $contract->isActive()
            ? __(':title sözleşmesini aktifleştirdi.', ['title' => $contract->title])
            : __(':title sözleşmesini pasifleştirdi.', ['title' => $contract->title]), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => $contract->isActive()
            ? __(':title aktifleştirildi.', ['title' => $contract->title])
            : __(':title pasifleştirildi.', ['title' => $contract->title])]);
    }

    public function destroy(Request $request, Contract $contract, CatalogService $catalog): RedirectResponse
    {
        $catalog->deleteContract($contract, $request->user());

        return to_route('panel.shop.contracts.index')
            ->with('toast', ['type' => 'success', 'message' => __(':title silindi.', ['title' => $contract->title])]);
    }
}
