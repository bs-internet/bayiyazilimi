<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\SaveCampaignRequest;
use App\Models\Campaign;
use App\Models\Currency;
use App\Models\Product;
use App\Services\Panel\Shop\CampaignService;
use App\Services\Shop\CurrencyManager;
use App\Support\Pagination;
use App\Support\Shop\Campaigns\CampaignAction;
use App\Support\Shop\Campaigns\CampaignCondition;
use App\Support\Shop\Campaigns\CampaignRegistry;
use App\Support\Shop\Campaigns\CampaignUsages;
use App\Support\Shop\Storefront;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Müşteriler › Kampanyalar: koşul (kime / hangi ürünlere) + işlem.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignRegistry $registry) {}

    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $action = $request->string('action')->toString();
        $now = CarbonImmutable::now();

        $campaigns = Campaign::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when(Status::tryFrom($status), fn ($q, Status $s) => $q->where('status', $s->value))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->ordered()
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString()
            ->through(fn (Campaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'action_label' => $this->registry->action($c->action)?->label() ?? $c->action,
                'summary' => $this->registry->summary($c),
                'starts_at' => $c->starts_at?->toIso8601String(),
                'ends_at' => $c->ends_at?->toIso8601String(),
                'live' => $c->isLiveAt($now),
                'status' => $c->status->value,
            ]);

        return Inertia::render('panel/shop/campaigns/index', [
            'campaigns' => $campaigns,
            'filters' => ['q' => $search, 'status' => $status, 'action' => $action],
            'actions' => $this->actionOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('panel/shop/campaigns/form', $this->formProps(null));
    }

    public function store(SaveCampaignRequest $request, CampaignService $service): RedirectResponse
    {
        $campaign = $service->save(null, $request->payload(), $request->user());

        return to_route('panel.shop.campaigns.edit', $campaign->id)
            ->with('toast', ['type' => 'success', 'message' => __(':name kampanyası oluşturuldu (pasif). Kontrol edip aktifleştirin.', ['name' => $campaign->name])]);
    }

    public function edit(Campaign $campaign): Response
    {
        return Inertia::render('panel/shop/campaigns/form', $this->formProps($campaign));
    }

    public function update(SaveCampaignRequest $request, Campaign $campaign, CampaignService $service): RedirectResponse
    {
        $service->save($campaign, $request->payload(), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __(':name kampanyası güncellendi.', ['name' => $campaign->name])]);
    }

    public function status(Request $request, Campaign $campaign, CampaignService $service): RedirectResponse
    {
        // Hedef durum zaten geçerliyse (çift tıklama / iki yönetici) değişiklik yapılmaz.
        if ($campaign->hasStatus($request->input('status'))) {
            return back();
        }

        $service->toggleStatus($campaign, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Kampanya durumu değiştirildi.')]);
    }

    public function destroy(Request $request, Campaign $campaign, CampaignService $service): RedirectResponse
    {
        $service->delete($campaign, $request->user());

        return to_route('panel.shop.campaigns.index')
            ->with('toast', ['type' => 'success', 'message' => __('Kampanya silindi.')]);
    }

    /**
     * Kullanım özeti: sipariş sayısı ve toplam kazanç (iptal / başarısız hariç).
     *
     * @return array{count: int, value: string}
     */
    private function usage(Campaign $campaign): array
    {
        $total = app(CampaignUsages::class)->totals([$campaign->id])[$campaign->id] ?? null;

        return [
            'count' => $total['count'] ?? 0,
            'value' => app(CurrencyManager::class)->base()->format($total['value'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Campaign $campaign): array
    {
        // "Vitrin etiketi" yalnız vitrin ürün kartlarında görünür; yalnız vitrin kuruluyken.
        $storefront = Storefront::enabled();

        $conditions = array_values(array_map(fn (CampaignCondition $c) => [
            'key' => $c->key(),
            'label' => $c->label(),
            'scope' => $c->scope(),
            'operators' => collect($c->operators())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'input' => $c->input(),
            'options' => $c->options(),
            'unit' => $c->unit(),
        ], $this->registry->conditions()));

        // Ürün seçicili değerlerin (koşullar, hediye) ad / kod bilgisi.
        $productIds = collect($campaign?->product_rules ?? [])
            ->flatMap(fn ($r) => is_array($r['value'] ?? null) && ($r['field'] ?? '') === 'product' ? $r['value'] : [])
            ->push($campaign?->settings()['product_id'] ?? null)
            ->filter()->unique()->values()->all();

        return [
            'campaign' => $campaign ? [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'badge' => $campaign->badge,
                'starts_at' => $campaign->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $campaign->ends_at?->format('Y-m-d\TH:i'),
                'sort_order' => $campaign->sort_order,
                'status' => $campaign->status->value,
                'audience_rules' => $campaign->audience_rules ?? [],
                'product_scope' => $campaign->product_scope,
                'product_rules' => $campaign->product_rules ?? [],
                'action' => $campaign->action,
                'shipping_mode' => $campaign->shipping_mode,
                'shipping_amount' => $campaign->shipping_amount,
                'currency_code' => $campaign->currency_code,
                'settings' => (object) $campaign->settings(),
                'combinable' => $campaign->combinables()->pluck('campaigns.id')->all(),
                'usage_limit' => $campaign->usage_limit,
                'usage_limit_per_customer' => $campaign->usage_limit_per_customer,
                'budget' => $campaign->budget,
                'usage' => $this->usage($campaign),
            ] : null,
            'conditions' => $conditions,
            'storefront' => $storefront,
            // Birlikte uygulanabilecek kampanya seçenekleri (kendisi hariç).
            'otherCampaigns' => Campaign::query()->when($campaign, fn ($q) => $q->whereKeyNot($campaign->id))->ordered()->orderBy('name')->get()
                ->map(fn (Campaign $c) => ['value' => $c->id, 'label' => $c->name.' — '.$this->registry->summary($c)])
                ->all(),
            // Tutarların para birimi seçenekleri; boşsa varsayılan para birimi.
            'currencies' => Currency::query()->active()->ordered()->get(['code', 'name', 'symbol'])
                ->map(fn (Currency $c) => ['value' => $c->code, 'label' => __(':code — :name', ['code' => $c->code, 'name' => $c->name]), 'symbol' => $c->symbol ?: $c->code])
                ->all(),
            'baseCurrency' => app(CurrencyManager::class)->base()->code,
            'actions' => array_values(array_map(fn (CampaignAction $a) => [
                'key' => $a->key(),
                'label' => $a->label(),
                'fields' => $a->fields(),
            ], $this->registry->actions())),
            'products' => $productIds === [] ? (object) [] : Product::query()->withTrashed()->whereIn('id', $productIds)
                ->get(['id', 'sku', 'name'])
                ->mapWithKeys(fn (Product $p) => [$p->id => ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name]])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function actionOptions(): array
    {
        return array_values(array_map(fn (CampaignAction $a) => ['value' => $a->key(), 'label' => $a->label()], $this->registry->actions()));
    }
}
