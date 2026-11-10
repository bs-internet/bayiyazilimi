<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Enums\Shop\QuoteStatus;
use App\Http\Controllers\App\Shop\Concerns\ResolvesMemberArea;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Quote;
use App\Models\QuoteHistory;
use App\Models\QuoteItem;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\OrderService;
use App\Services\Shop\QuoteService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Üye alanı: Tekliflerim (bayi /app, müşteri vitrindeki Hesabım). Müşterinin
 * talepleri ve yöneticinin ona hazırladığı teklifler; gönderilen teklifi
 * onaylar, revize ister ya da reddeder; onaylıyı öder (sipariş oluşur).
 */
class QuoteController extends Controller
{
    use ResolvesMemberArea;

    public function index(Request $request): Response
    {
        $quotes = $this->query($request)
            ->withCount('items')
            ->latest()
            ->paginate(Pagination::PER_PAGE)
            ->through(fn (Quote $q) => [
                'id' => $q->id,
                'number' => $q->number,
                'status' => $q->status->value,
                'status_label' => $q->customerStatusLabel(),
                'from_store' => $q->isAdminCreated(),
                'items_count' => $q->items_count,
                'grand_total' => $q->status->isPriced() ? OrderController::money($q->grand_total, $q->currency_code) : null,
                'needs_action' => $q->canRespond() || $q->canBeOrdered(),
                'valid_until' => $q->valid_until?->toDateString(),
                'created_at' => $q->created_at?->toIso8601String(),
            ]);

        return Inertia::render($this->page('quotes/index'), [
            'quotes' => $quotes,
        ]);
    }

    public function show(Request $request, Quote $quote): Response
    {
        $this->authorizeOwner($request, $quote);

        return Inertia::render($this->page('quotes/show'), [
            'quote' => self::present($quote, fn (string $name) => route($this->areaRoute("quotes.{$name}"), $quote->id), fn (string $name, string $id) => route($this->areaRoute($name), $id)),
        ]);
    }

    public function approve(Request $request, Quote $quote, QuoteService $quotes): RedirectResponse
    {
        $this->authorizeOwner($request, $quote);
        $quotes->approve($quote, $request->user(), self::note($request));

        return redirect()->route($this->areaRoute('quotes.checkout'), $quote->id)
            ->with('success', __('Teklifi onayladınız. Ödemeyi tamamlayarak siparişinizi oluşturabilirsiniz.'));
    }

    public function revision(Request $request, Quote $quote, QuoteService $quotes): RedirectResponse
    {
        $this->authorizeOwner($request, $quote);
        $quotes->requestRevision($quote, $request->user(), self::note($request, required: true));

        return back()->with('success', __('Revize isteğiniz iletildi; güncel teklif size e-postayla gönderilecek.'));
    }

    public function reject(Request $request, Quote $quote, QuoteService $quotes): RedirectResponse
    {
        $this->authorizeOwner($request, $quote);
        $quotes->reject($quote, $request->user(), self::note($request));

        return back()->with('success', __('Teklifi reddettiniz.'));
    }

    public function withdraw(Request $request, Quote $quote, QuoteService $quotes): RedirectResponse
    {
        $this->authorizeOwner($request, $quote);
        $quotes->withdraw($quote, $request->user());

        return back()->with('success', __('Teklif talebiniz geri çekildi.'));
    }

    public function checkout(Request $request, Quote $quote, CurrencyManager $currencies): Response|RedirectResponse
    {
        $this->authorizeOwner($request, $quote);
        $showUrl = route($this->areaRoute('quotes.show'), $quote->id);

        if (! $quote->canBeOrdered()) {
            return redirect()->to($showUrl)->with('warning', __('Bu teklif şu an ödenemez.'));
        }

        return Inertia::render($this->checkoutPage(), [
            ...self::checkoutProps($quote, $currencies, $request),
            'action' => route($this->areaRoute('quotes.place-order'), $quote->id),
            'quote' => null,
            'context' => [
                'title' => __('Teklif :number — Ödeme', ['number' => $quote->number]),
                'back_label' => __('Teklif :number', ['number' => $quote->number]),
                'back_url' => $showUrl,
            ],
        ]);
    }

    public function placeOrder(CheckoutRequest $request, Quote $quote, OrderService $orders): RedirectResponse|SymfonyResponse
    {
        $this->authorizeOwner($request, $quote);
        CheckoutController::ensureMethodAvailable($request->input('payment_method'), $request->user());

        $order = $orders->placeFromQuote($quote, $request->orderData(), $request->user(), $request->ip());
        CheckoutController::rememberAddress($request);

        return CheckoutController::redirectAfterPlaced($order, $this->placedUrl($order));
    }

    /**
     * Müşteri ekranı için teklif (üye alanı ve imzalı bağlantı ortak).
     *
     * @param  callable(string): string  $action  işlem adı → URL (approve, revision, reject, withdraw, checkout)
     * @param  callable(string, string): string|null  $route  rota adı + id → URL (sipariş bağlantısı)
     * @return array<string, mixed>
     */
    public static function present(Quote $quote, callable $action, ?callable $route = null): array
    {
        $quote->load(['items.product.images', 'order', 'histories']);
        $priced = $quote->status->isPriced();
        $code = $quote->currency_code;
        $money = fn ($v) => OrderController::money($v, $code);

        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'status' => $quote->status->value,
            'status_label' => $quote->customerStatusLabel(),
            'from_store' => $quote->isAdminCreated(),
            'priced' => $priced,
            'customer_note' => $quote->customer_note,
            'admin_note' => $priced ? $quote->admin_note : null,
            'valid_from' => $quote->valid_from?->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'expired' => $quote->isExpired(),
            'not_started' => $quote->notStarted(),
            'created_at' => $quote->created_at?->toIso8601String(),
            'subtotal' => $priced ? $money($quote->subtotal) : null,
            'tax_total' => $priced ? $money($quote->tax_total) : null,
            'shipping_total' => $priced ? (Money::decimal($quote->shipping_total)->isPositive() ? $money($quote->shipping_total) : __('Ücretsiz')) : null,
            'grand_total' => $priced ? $money($quote->grand_total) : null,
            'items' => $quote->items->map(fn (QuoteItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'sku' => $i->sku,
                'slug' => $i->product?->slug,
                'image' => $i->product?->coverUrl(),
                'quantity' => $i->quantity,
                'unit' => $i->unit,
                'unit_price' => $priced && $i->unit_price !== null ? $money($i->unit_price) : null,
                'tax_rate' => Money::decimal($i->tax_rate),
                'line_total' => $priced ? $money($i->line_total) : null,
            ])->values(),
            'history' => $quote->histories
                ->reject(fn (QuoteHistory $h) => $h->status === QuoteStatus::Draft)
                ->map(fn (QuoteHistory $h) => [
                    'id' => $h->id,
                    'label' => self::historyLabel($h),
                    'note' => $h->note,
                    'by_customer' => $h->by_customer,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->values(),
            'order' => $quote->order ? [
                'number' => $quote->order->number,
                'url' => $route !== null ? $route('orders.show', $quote->order->id) : $quote->order->publicUrl(),
            ] : null,
            'actions' => [
                'approve' => $quote->canRespond() ? $action('approve') : null,
                'revision' => $quote->canRespond() ? $action('revision') : null,
                'reject' => $quote->canRespond() ? $action('reject') : null,
                'withdraw' => $quote->canWithdraw() ? $action('withdraw') : null,
                'checkout' => $quote->canBeOrdered() ? $action('checkout') : null,
            ],
        ];
    }

    /**
     * Ödeme ekranının teklif tutarlarıyla ortak alanları.
     *
     * @return array<string, mixed>
     */
    public static function checkoutProps(Quote $quote, CurrencyManager $currencies, Request $request): array
    {
        $quote->load('items');
        $currency = $currencies->find($quote->currency_code) ?? $currencies->base();
        $shipping = Money::decimal($quote->shipping_total);

        return [
            'lines' => CheckoutController::summaryLines(
                $quote->items->map(fn (QuoteItem $i) => ['name' => $i->name, 'quantity' => $i->quantity, 'total' => $i->line_total])->all(),
                $currency,
            ),
            'totals' => [
                // Kampanyalar teklife uygulanmaz.
                'discount' => Money::round(0),
                'discount_formatted' => null,
                'subtotal' => Money::decimal($quote->subtotal),
                'subtotal_formatted' => $currency->format($quote->subtotal),
                'tax' => Money::decimal($quote->tax_total),
                'tax_formatted' => $currency->format($quote->tax_total),
                'shipping' => $shipping,
                'shipping_formatted' => $shipping->isPositive() ? $currency->format($shipping) : __('Ücretsiz'),
                'total' => Money::decimal($quote->grand_total),
                'total_formatted' => $currency->format($quote->grand_total),
                'desi' => null,
                'carrier' => null,
                'currency' => $currency->code,
            ],
            'carriers' => [],
            'methods' => CheckoutController::paymentMethods($request->user(), Money::decimal($quote->grand_total), $currency),
            'prefill' => CheckoutController::prefill($request->user()),
            'checkoutToken' => CheckoutController::newCheckoutToken(),
            'contracts' => CheckoutController::contracts(),
            ...CheckoutController::addressProps($request->user()),
        ];
    }

    public static function note(Request $request, bool $required = false): ?string
    {
        $data = $request->validate([
            'note' => [$required ? 'required' : 'nullable', 'string', 'max:2000'],
        ], [
            'note.required' => __('Neyin değişmesini istediğinizi yazın.'),
        ]);

        return $data['note'] ?? null;
    }

    private static function historyLabel(QuoteHistory $history): string
    {
        return match ($history->status) {
            QuoteStatus::Pending => __('Teklif talebiniz alındı'),
            QuoteStatus::Revision => __('Revize istediniz'),
            QuoteStatus::Sent => __('Teklif size gönderildi'),
            QuoteStatus::Approved => $history->by_customer ? __('Teklifi onayladınız') : __('Teklif yeniden ödenebilir'),
            QuoteStatus::Ordered => __('Siparişe dönüştü'),
            QuoteStatus::Rejected => __('Teklifi reddettiniz'),
            QuoteStatus::Cancelled => $history->by_customer ? __('Talebinizi geri çektiniz') : __('Teklif iptal edildi'),
            QuoteStatus::Draft => '',
        };
    }

    /**
     * @return Builder<Quote>
     */
    private function query(Request $request): Builder
    {
        $user = $request->user();

        return Quote::query()->visibleToCustomer()->where(fn ($q) => $user->account_id !== null
            ? $q->where('account_id', $user->account_id)
            : $q->where('user_id', $user->id));
    }

    private function authorizeOwner(Request $request, Quote $quote): void
    {
        $user = $request->user();
        $owns = $user !== null && $quote->status !== QuoteStatus::Draft
            && ($quote->user_id === $user->id || ($quote->account_id !== null && $quote->account_id === $user->account_id));

        abort_unless($owns, 404);
    }
}
