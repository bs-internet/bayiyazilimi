<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\PaymentTerms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveDealerGroupRequest;
use App\Models\DealerGroup;
use App\Services\Panel\Shop\DealerService;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DealerGroupController extends Controller
{
    public function index(): Response
    {
        $groups = DealerGroup::query()->withCount('accounts')->orderBy('name')->get();

        return Inertia::render('panel/members/dealer-groups', [
            'groups' => $groups->map(fn (DealerGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'discount_rate' => Money::decimal($g->discount_rate),
                'description' => $g->description,
                'dealers_count' => $g->accounts_count,
                'created_at' => $g->created_at,
                'terms' => CreditTerms::raw($g),
                'terms_label' => (PaymentTerms::tryFrom((string) $g->payment_terms) ?? PaymentTerms::Prepaid)->label(),
            ])->all(),
            'termOptions' => CreditTerms::options(),
        ]);
    }

    public function store(SaveDealerGroupRequest $request, DealerService $dealers): RedirectResponse
    {
        $group = $dealers->saveGroup(null, $request->validated(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name bayi grubu oluşturuldu.', ['name' => $group->name])]);
    }

    public function update(SaveDealerGroupRequest $request, DealerGroup $dealerGroup, DealerService $dealers): RedirectResponse
    {
        $dealers->saveGroup($dealerGroup, $request->validated(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name bayi grubu güncellendi.', ['name' => $dealerGroup->name])]);
    }

    public function destroy(Request $request, DealerGroup $dealerGroup, DealerService $dealers): RedirectResponse
    {
        $dealers->deleteGroup($dealerGroup, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name bayi grubu silindi.', ['name' => $dealerGroup->name])]);
    }
}
