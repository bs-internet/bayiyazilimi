<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Enums\Shop\QuoteStatus;
use App\Enums\Status;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Shop\Concerns\BuildsShopOptions;
use App\Http\Requests\Panel\Shop\SaveQuoteRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\Panel\Shop\QuoteResource;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteHistory;
use App\Models\QuoteItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\QuoteService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mağaza › Teklifler: müşteri talepleri ve yöneticinin hazırladığı teklifler.
 * Yönetici kalemleri / fiyatları düzenler ve müşteriye gönderir; onay, revize
 * ve ret müşteri tarafında yapılır.
 */
class QuoteController extends Controller
{
    use BuildsShopOptions;

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'source', 'account']);

        $quotes = Quote::query()
            ->withCount('items')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', "%{$v}%")
                ->orWhere('customer_name', 'like', "%{$v}%")
                ->orWhere('company', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->when($filters['status'] ?? null, fn ($q, $v) => $v === 'awaiting'
                ? $q->whereIn('status', QuoteStatus::awaitingAdmin())
                : $q->where('status', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['account'] ?? null, fn ($q, $v) => $q->where('account_id', $v))
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->withQueryString();

        return Inertia::render('panel/shop/quotes/index', [
            'quotes' => PaginatedResource::make($quotes, QuoteResource::class, $request),
            'filters' => $filters,
            'statuses' => [
                ['value' => 'awaiting', 'label' => __('İşlem bekleyenler')],
                ...QuoteStatus::options(),
            ],
            'sources' => [
                ['value' => Quote::SOURCE_REQUEST, 'label' => __('Müşteri talebi')],
                ['value' => Quote::SOURCE_ADMIN, 'label' => __('Yönetici teklifi')],
            ],
            'awaiting' => Quote::query()->whereIn('status', QuoteStatus::awaitingAdmin())->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('panel/shop/quotes/form', $this->formProps(null, $request->string('customer')->toString() ?: null));
    }

    public function store(SaveQuoteRequest $request, QuoteService $service): RedirectResponse
    {
        $quote = $service->save(null, $request->validated(), $request->user(), $request->boolean('send'));

        if ($request->boolean('send')) {
            $service->send($quote, $request->user());

            return to_route('panel.shop.quotes.edit', $quote->id)
                ->with('toast', ['type' => 'success', 'message' => __(':number teklifi müşteriye gönderildi.', ['number' => $quote->number])]);
        }

        return to_route('panel.shop.quotes.edit', $quote->id)
            ->with('toast', ['type' => 'success', 'message' => __(':number teklifi taslak olarak kaydedildi.', ['number' => $quote->number])]);
    }

    public function edit(Quote $quote): Response
    {
        return Inertia::render('panel/shop/quotes/form', $this->formProps($quote));
    }

    public function update(SaveQuoteRequest $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $service->save($quote, $request->validated(), $request->user(), $request->boolean('send'));

        if ($request->boolean('send')) {
            $service->send($quote->refresh(), $request->user());

            return back()->with('toast', ['type' => 'success', 'message' => __(':number teklifi müşteriye gönderildi.', ['number' => $quote->number])]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __(':number teklifi kaydedildi.', ['number' => $quote->number])]);
    }

    public function cancel(Request $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $service->cancel($quote, $request->user(), $data['note'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => __(':number teklifi iptal edildi.', ['number' => $quote->number])]);
    }

    public function destroy(Request $request, Quote $quote, QuoteService $service): RedirectResponse
    {
        $service->delete($quote, $request->user());

        return to_route('panel.shop.quotes.index')
            ->with('toast', ['type' => 'success', 'message' => __(':number taslağı silindi.', ['number' => $quote->number])]);
    }

    /**
     * Ürün seçici: arama + kategori (alt kategoriler dahil) / marka süzgeci.
     * Fiyatlar KDV hariç, istenen para biriminde (varsayılan: ana para birimi).
     */
    public function products(Request $request, CurrencyManager $currencies): JsonResponse
    {
        $categoryIds = null;

        if ($categoryId = $request->string('category_id')->toString()) {
            $category = Category::query()->find($categoryId);
            $categoryIds = $category ? [$category->id, ...$category->descendantIds()] : [];
        }

        $target = $currencies->find($request->string('currency')->toString() ?: null) ?? $currencies->base();

        $items = Product::query()
            ->with(['currency', 'images'])
            ->where('status', Status::Active->value)
            ->search($request->string('q')->toString())
            ->when($categoryIds !== null, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->when($request->string('brand_id')->toString(), fn ($q, $brand) => $q->where('brand_id', $brand))
            ->orderBy('name')
            ->limit(40)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit,
                'image' => $p->coverUrl(),
                'tax_rate' => Money::decimal($p->tax_rate),
                'price' => Money::round($currencies->convert($p->price, $p->currency, $target)),
                'stock' => (int) $p->stock,
            ])
            ->all();

        return response()->json(['data' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Quote $quote, ?string $customerId = null): array
    {
        $quote?->load(['items.product.images', 'order', 'user', 'creator', 'histories.user']);
        $currency = $quote ? Currency::withTrashed()->where('code', $quote->currency_code)->first() : null;

        return [
            'quote' => $quote ? [
                'id' => $quote->id,
                'number' => $quote->number,
                'source' => $quote->source,
                'status' => $quote->status->value,
                'status_label' => $quote->status->label(),
                'editable' => $quote->isEditable(),
                'can_cancel' => ! $quote->status->isFinal() && $quote->order_id === null,
                'can_delete' => $quote->status === QuoteStatus::Draft,
                'expired' => $quote->isExpired(),
                'user_id' => $quote->user_id,
                'customer' => [
                    'name' => $quote->customer_name,
                    'company' => $quote->company,
                    'email' => $quote->email,
                    'phone' => $quote->phone,
                    'city' => $quote->city,
                    'member' => $quote->user_id !== null,
                ],
                'customer_note' => $quote->customer_note,
                'admin_note' => $quote->admin_note,
                'currency_code' => $quote->currency_code,
                'currency_symbol' => $currency?->symbol,
                'valid_from' => $quote->valid_from?->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
                'shipping_total' => Money::decimal($quote->shipping_total),
                'sent_at' => $quote->sent_at?->toIso8601String(),
                'created_at' => $quote->created_at?->toIso8601String(),
                'creator' => $quote->creator?->name,
                'public_url' => $quote->publicUrl(),
                'order' => $quote->order ? ['id' => $quote->order->id, 'number' => $quote->order->number] : null,
                'items' => $quote->items->map(fn (QuoteItem $i) => [
                    'product_id' => $i->product_id,
                    'sku' => $i->sku,
                    'name' => $i->name,
                    'unit' => $i->unit,
                    'image' => $i->product?->coverUrl(),
                    'tax_rate' => Money::decimal($i->tax_rate),
                    'quantity' => $i->quantity,
                    'unit_price' => $i->unit_price !== null ? Money::decimal($i->unit_price) : '',
                    'list_price' => Money::decimal($i->list_price),
                    'stock' => $i->product ? (int) $i->product->stock : null,
                ])->filter(fn (array $i) => $i['product_id'] !== null)->values()->all(),
                'histories' => $quote->histories->map(fn (QuoteHistory $h) => [
                    'id' => $h->id,
                    'status' => $h->status->value,
                    'label' => $this->historyLabel($h),
                    'note' => $h->note,
                    'by_customer' => $h->by_customer,
                    'user' => $h->user?->name,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->all(),
            ] : null,
            'defaultCustomer' => $customerId,
            'customers' => User::query()
                ->where('user_type', UserType::Member->value)
                ->with('account')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'account_id', 'user_type'])
                ->map(fn (User $u) => [
                    'value' => $u->id,
                    'label' => "{$u->name} — {$u->email}".($u->isDealer() ? __(' (Bayi)') : ''),
                ])
                ->all(),
            'currencies' => app(CurrencyManager::class)->active()
                ->map(fn (Currency $c) => ['value' => $c->code, 'label' => __(':code (:symbol)', ['code' => $c->code, 'symbol' => $c->symbol])])
                ->values()->all(),
            'defaultCurrency' => app(CurrencyManager::class)->base()->code,
            'categories' => $this->categoryOptions(),
            'brands' => $this->brandOptions(),
            'units' => Unit::options(),
        ];
    }

    private function historyLabel(QuoteHistory $history): string
    {
        return match ($history->status) {
            QuoteStatus::Draft => __('Taslak oluşturuldu'),
            QuoteStatus::Pending => __('Müşteri teklif istedi'),
            QuoteStatus::Revision => __('Müşteri revize istedi'),
            QuoteStatus::Sent => __('Teklif müşteriye gönderildi'),
            QuoteStatus::Approved => $history->by_customer ? __('Müşteri onayladı') : __('Teklif yeniden ödenebilir'),
            QuoteStatus::Ordered => __('Siparişe dönüştü'),
            QuoteStatus::Rejected => __('Müşteri reddetti'),
            QuoteStatus::Cancelled => $history->by_customer ? __('Müşteri talebini geri çekti') : __('İptal edildi'),
        };
    }
}
