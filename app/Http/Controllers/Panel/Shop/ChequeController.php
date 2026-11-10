<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\ChequeStatus;
use App\Enums\Shop\CollectionType;
use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Services\Shop\ChequeService;
use App\Services\Shop\ShopSettings;
use App\Support\Money;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel › Ödemeler › Çek / Senet portföyü (Cari Ayarları'ndan açılır). Çek / senet
 * müşteri detayındaki "Tahsilat ekle" ile girilir; burada durum takibi yapılır.
 */
class ChequeController extends Controller
{
    public function index(Request $request, ChequeService $cheques): Response
    {
        abort_unless(ShopSettings::bool('cheques_enabled'), 404);

        $filters = $request->only(['status', 'type', 'due', 'q']);
        $warning = ShopSettings::int('cheque_warning_days');
        $today = CarbonImmutable::today();
        $escaped = addcslashes((string) ($filters['q'] ?? ''), '%_\\');

        $list = Cheque::query()
            ->with(['account:id,title,code', 'account.users' => fn ($q) => $q->select('id', 'account_id', 'name'), 'media'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v), fn ($q) => $q->whereIn('status', ChequeStatus::atRisk()))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when(($filters['due'] ?? '') === 'soon', fn ($q) => $q->whereDate('due_date', '<=', $today->addDays($warning)))
            ->when($escaped !== '', fn ($q) => $q->where(fn ($w) => $w->where('serial_no', 'like', "%{$escaped}%")->orWhere('drawer', 'like', "%{$escaped}%")
                ->orWhereHas('account', fn ($a) => $a->where('title', 'like', "%{$escaped}%"))))
            ->orderBy('due_date')
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        return Inertia::render('panel/shop/ledger/cheques', [
            'cheques' => $list->through(fn (Cheque $c) => [
                'id' => $c->id,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'serial_no' => $c->serial_no,
                'bank_name' => $c->bank_name,
                'branch' => $c->branch,
                'drawer' => $c->drawer,
                'amount' => Money::format($c->amount),
                'due_date' => $c->due_date->toDateString(),
                'days' => (int) $today->diffInDays($c->due_date, false),
                'soon' => in_array($c->status->value, ChequeStatus::atRisk(), true) && $c->due_date->lte($today->addDays($warning)),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'next' => array_map(fn (ChequeStatus $s) => ['value' => $s->value, 'label' => $s->label()], $c->status->next()),
                'endorsed_to' => $c->endorsed_to,
                'notes' => $c->notes,
                'account' => $c->account ? ['id' => $c->account->id, 'title' => $c->account->title ?? $c->account->code, 'member_id' => $c->account->users->first()?->id] : null,
                'has_image' => $c->media->contains('collection', Cheque::IMAGE),
            ]),
            'filters' => $filters,
            'statuses' => ChequeStatus::options(),
            'types' => [
                ['value' => CollectionType::Cheque->value, 'label' => CollectionType::Cheque->label()],
                ['value' => CollectionType::PromissoryNote->value, 'label' => CollectionType::PromissoryNote->label()],
            ],
            'totals' => $cheques->totals(),
            'warningDays' => $warning,
        ]);
    }

    public function status(Request $request, Cheque $cheque, ChequeService $cheques): RedirectResponse
    {
        abort_unless(ShopSettings::bool('cheques_enabled'), 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ChequeStatus::class)],
            'endorsed_to' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['status' => __('durum'), 'endorsed_to' => __('ciro edilen'), 'note' => __('not')]);

        $status = ChequeStatus::from($data['status']);
        $cheques->changeStatus($cheque, $status, $request->user(), $data['endorsed_to'] ?? null, $data['note'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => __(':serial: :status', ['serial' => $cheque->serial_no, 'status' => $status->label()])]);
    }

    public function image(Cheque $cheque): RedirectResponse
    {
        $media = $cheque->firstMediaIn(Cheque::IMAGE);

        abort_if($media === null, 404);

        return redirect()->away((string) $media->temporaryUrl());
    }
}
