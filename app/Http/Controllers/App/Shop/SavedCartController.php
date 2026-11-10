<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\SavedCart;
use App\Services\Shop\Basket;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\OrderService;
use App\Services\Shop\SavedCartService;
use App\Services\Shop\ShippingService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Bayi paneli: Kayıtlı Sepetler. Kayıtlar hesap bazlıdır (BelongsToAccount).
 * "Sipariş ver" bayinin asıl sepetine dokunmadan, kayıtlı sepetin ürünleriyle
 * (güncel fiyat / stok / kampanya) ödeme ekranını açar; "Sepeti güncelle"
 * kayıtlı sepetin adetlerini değiştirir.
 */
class SavedCartController extends Controller
{
    /**
     * Sepeti güncelle: kayıtlı sepetin adetleri (0 → ürün çıkar).
     */
    public function items(Request $request, SavedCart $savedCart, SavedCartService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $savedCart);
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:100000'],
        ]);

        $service->updateQuantities($savedCart, $request->user(), $data['quantities']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Kayıtlı sepet güncellendi.')]);
    }

    /**
     * Sipariş ver: kayıtlı sepet ayrı bir sipariş sepetine alınır ve ödeme
     * ekranı açılır. Eklenemeyen ürün varsa önce sepetin düzeltilmesi istenir.
     */
    public function checkout(Request $request, SavedCart $savedCart, SavedCartService $service, CurrencyManager $currencies, ShippingService $shipping): Response|RedirectResponse
    {
        $this->authorizeOwner($request, $savedCart);
        $basket = Basket::savedCheckout($savedCart->id);
        $result = $service->loadIntoBasket($savedCart, $basket, 'replace', $request->user());
        $showUrl = route('app.shop.saved-carts.show', $savedCart->id);

        if ($result['failed'] !== []) {
            $basket->clear();

            return redirect()->to($showUrl)->with('toast', [
                'type' => 'warning',
                'message' => __(':count ürün siparişe eklenemiyor (satış dışı, yalnız teklifle satılıyor ya da stok yetersiz). Adetleri güncelleyin ya da ürünleri çıkarın.', ['count' => count($result['failed'])]),
            ]);
        }

        return Inertia::render('app/shop/checkout/index', CheckoutController::basketCheckoutProps(
            $request,
            $basket,
            $currencies->current(),
            $shipping,
            route('app.shop.saved-carts.place-order', $savedCart->id),
            [
                'title' => __(':name — Sipariş', ['name' => $savedCart->name]),
                'back_label' => $savedCart->name,
                'back_url' => $showUrl,
            ],
        ));
    }

    public function placeOrder(CheckoutRequest $request, SavedCart $savedCart, OrderService $orders, CurrencyManager $currencies): RedirectResponse|SymfonyResponse
    {
        $this->authorizeOwner($request, $savedCart);
        CheckoutController::ensureMethodAvailable($request->input('payment_method'), $request->user());

        $basket = Basket::savedCheckout($savedCart->id);

        if ($basket->isEmpty()) {
            return to_route('app.shop.saved-carts.checkout', $savedCart->id);
        }

        $order = $orders->placeFromCart($basket, $request->orderData(), $request->user(), $currencies->current(), $request->ip());
        CheckoutController::rememberAddress($request);

        return CheckoutController::redirectAfterPlaced($order, route('app.shop.orders.show', $order->id));
    }

    public function index(CurrencyManager $currencies, SavedCartService $service): Response
    {
        $currency = $currencies->current();
        $carts = SavedCart::query()->with('user:id,name')->latest()->paginate(Pagination::PER_PAGE);
        $estimates = $service->estimates($carts->getCollection(), $currency);

        return Inertia::render('app/shop/saved-carts/index', [
            'savedCarts' => $carts->through(fn (SavedCart $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'note' => $c->note,
                'item_count' => $estimates[$c->id]['count'],
                'estimated_total' => $estimates[$c->id]['total'],
                'estimated_total_formatted' => $currency->format($estimates[$c->id]['total']),
                'created_by' => $c->user?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'cartCount' => Basket::cart()->count(),
        ]);
    }

    public function show(Request $request, SavedCart $savedCart, CurrencyManager $currencies, SavedCartService $service): Response
    {
        $this->authorizeOwner($request, $savedCart);
        $currency = $currencies->current();
        $lines = $service->lines($savedCart, $currency);
        $total = Money::add(...$lines->map(fn (array $l) => $l['line_gross'] ?? 0));

        return Inertia::render('app/shop/saved-carts/show', [
            'savedCart' => [
                'id' => $savedCart->id,
                'name' => $savedCart->name,
                'note' => $savedCart->note,
                'created_at' => $savedCart->created_at?->toIso8601String(),
                'updated_at' => $savedCart->updated_at?->toIso8601String(),
                'created_by' => $savedCart->loadMissing('user:id,name')->user?->name,
                'item_count' => $savedCart->itemCount(),
                'estimated_total_formatted' => $currency->format($total),
                'has_warnings' => $lines->contains(fn (array $l) => ! $l['available'] || ($l['warning'] ?? null) !== null),
            ],
            'lines' => $lines,
        ]);
    }

    public function store(Request $request, SavedCartService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $saved = $service->saveFromBasket($request->user(), Basket::cart(), $data['name'], $data['note'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => __('Sepetiniz ":name" adıyla kaydedildi.', ['name' => $saved->name])]);
    }

    public function update(Request $request, SavedCart $savedCart, SavedCartService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $savedCart);
        $data = $this->validated($request);
        $service->update($savedCart, $request->user(), $data['name'], $data['note'] ?? null);

        return back()->with('toast', ['type' => 'success', 'message' => __('Kayıtlı sepet güncellendi.')]);
    }

    public function destroy(Request $request, SavedCart $savedCart, SavedCartService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $savedCart);
        $service->delete($savedCart, $request->user());

        return to_route('app.shop.saved-carts.index')->with('toast', ['type' => 'success', 'message' => __('Kayıtlı sepet silindi.')]);
    }

    /**
     * Rota bağlama hesap kapsamından önce çalışabilir; sahipliği açıkça doğrula.
     */
    private function authorizeOwner(Request $request, SavedCart $savedCart): void
    {
        abort_unless($savedCart->account_id === $request->user()?->account_id, 404);
    }

    /**
     * @return array{name: string, note?: ?string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => __('sepet adı'), 'note' => __('not')]);
    }
}
