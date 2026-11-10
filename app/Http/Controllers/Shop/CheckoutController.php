<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Enums\Shop\AddressType;
use App\Enums\UserType;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\App\Shop\AddressController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\ResolvesBasketArea;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Http\Resources\Shop\BasketPresenter;
use App\Models\Carrier;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Services\Shop\AddressService;
use App\Services\Shop\Basket;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShippingService;
use App\Support\Money;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\ProvidesInstallments;
use App\Support\RegionOptions;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Sepetten ödemenin ortak kuralları ve ödeme ekranı verisi (statik yardımcılar
 * kayıtlı sepet / teklif ödemesinde de kullanılır). Alan (sayfa, sipariş sonrası
 * adres) alt sınıfta: bayi paneli App\Http\Controllers\App\Shop\CheckoutController,
 * vitrin modülünde kendi CheckoutController'ı.
 */
abstract class CheckoutController extends Controller
{
    use ResolvesBasketArea;

    /**
     * Ödeme sayfasının Inertia adı.
     */
    abstract protected function page(): string;

    /**
     * Sipariş oluştuktan sonra gidilecek adres.
     */
    abstract protected function placedUrl(Order $order): string;

    public function index(Request $request, CurrencyManager $currencies, ShippingService $shipping): Response|RedirectResponse
    {
        $cart = Basket::cart();

        if ($cart->isEmpty()) {
            return to_route($this->basketRoute('cart.index'))->with('warning', __('Sepetiniz boş.'));
        }

        return Inertia::render(
            $this->page(),
            self::basketCheckoutProps($request, $cart, $currencies->current(), $shipping, route($this->basketRoute('checkout.store'))),
        );
    }

    /**
     * Bir sepetin ödeme ekranı verisi (asıl sepet ya da kayıtlı sepetten
     * sipariş sepeti).
     *
     * @param  array{title: string, back_label: string, back_url: string}|null  $context
     * @return array<string, mixed>
     */
    public static function basketCheckoutProps(Request $request, Basket $cart, Currency $currency, ShippingService $shipping, string $action, ?array $context = null): array
    {
        // Ekrandaki tutar, siparişte kullanılacak bilgilerle (önden doldurulan e-posta
        // ve il) hesaplanır: il / e-postaya bağlı kampanya ekranda ve siparişte aynı olur.
        // Müşteri formda başka il seçerse tutar sipariş anında yeniden doğrulanır (CheckoutGuard).
        $prefill = self::prefill($request->user());
        $email = $prefill['email'] !== '' ? $prefill['email'] : null;
        $city = ($prefill['shipping']['city'] ?? '') !== '' ? $prefill['shipping']['city'] : (($prefill['billing']['city'] ?? '') !== '' ? $prefill['billing']['city'] : null);

        $cart->useCheckout($email, null, $city);
        $view = self::basketView($cart, $currency, $shipping);
        $methods = self::paymentMethods($request->user(), $view['raw_total'], $currency);

        // Ödeme yöntemine bağlı kampanya (ör. havale / EFT ile ödemede): yöntem seçilince
        // gösterilecek sepet. Yalnız sonucu değiştiren yöntemler gönderilir.
        $methodTotals = [];
        foreach ($methods as $method) {
            $cart->useCheckout($email, $method['value'], $city);
            $alt = self::basketView($cart, $currency, $shipping);

            if ($alt['totals'] != $view['totals']) {
                $methodTotals[$method['value']] = ['lines' => $alt['lines'], 'totals' => $alt['totals'], 'carriers' => $alt['carriers']];
            }
        }
        $cart->useCheckout(null, null, null);

        return [
            'lines' => $view['lines'],
            'totals' => $view['totals'],
            'carriers' => $view['carriers'],
            'methodTotals' => (object) $methodTotals,
            'methods' => $methods,
            'prefill' => $prefill,
            'action' => $action,
            'checkoutToken' => self::newCheckoutToken(),
            'quote' => null,
            'context' => $context,
            'contracts' => self::contracts(),
            ...self::addressProps($request->user()),
        ];
    }

    /**
     * Sepetin ödeme ekranındaki özeti: satırlar, toplamlar ve kargo seçenekleri.
     *
     * @return array{lines: array<int, mixed>, totals: array<string, mixed>, carriers: array<int, mixed>, raw_total: BigDecimal}
     */
    private static function basketView(Basket $cart, Currency $currency, ShippingService $shipping): array
    {
        $lines = $cart->lines($currency);
        $totals = $cart->totals($currency);

        return [
            'lines' => self::summaryLines($lines->map(fn (array $l) => [
                'name' => $l['product']->name.($l['gift'] ? __(' (Hediye)') : ''),
                'quantity' => $l['quantity'],
                'total' => $l['line_gross'],
                'campaign' => $l['campaign'],
            ])->all(), $currency),
            'totals' => BasketPresenter::totals($totals, $currency),
            'carriers' => self::carrierOptions($lines, Money::add($totals['subtotal'], $totals['tax']), $currency, $shipping),
            'raw_total' => $totals['total'],
        ];
    }

    public function store(CheckoutRequest $request, OrderService $orders, CurrencyManager $currencies): RedirectResponse|SymfonyResponse
    {
        self::ensureMethodAvailable($request->input('payment_method'), $request->user());

        $order = $orders->placeFromCart(
            Basket::cart(),
            $request->orderData(),
            $request->user(),
            $currencies->current(),
            $request->ip(),
        );

        self::rememberAddress($request);

        return self::redirectAfterPlaced($order, $this->placedUrl($order));
    }

    /**
     * Ödeme formunun adres seçimleri: il listesi, üyenin kayıtlı adresleri.
     *
     * @return array{cities: array<int, array{id: string, name: string}>, savedAddresses: array<int, array<string, mixed>>, canSaveAddress: bool}
     */
    public static function addressProps(?User $user): array
    {
        $member = $user !== null && $user->user_type === UserType::Member && $user->account_id !== null;

        $addresses = $member
            ? CustomerAddress::query()
                ->where('account_id', $user->account_id)
                ->with(['city:id,name', 'district:id,name'])
                ->orderByDesc('is_default_billing')
                ->orderByDesc('is_default_shipping')
                ->get()
                ->map(fn (CustomerAddress $a) => AddressController::present($a))
                ->values()
                ->all()
            : [];

        return [
            'cities' => RegionOptions::cities(),
            'savedAddresses' => $addresses,
            'canSaveAddress' => $member,
        ];
    }

    /**
     * Üye "adreslerime kaydet" dediyse teslimat adresini teslimat, fatura adresini
     * fatura türünde (iki ayrı kayıt) Adreslerim'e ekler.
     */
    public static function rememberAddress(CheckoutRequest $request): void
    {
        $user = $request->user();

        if (! $request->boolean('save_address') || $user === null || $user->user_type !== UserType::Member) {
            return;
        }

        $data = $request->orderData();

        app(AddressService::class)->saveFromCheckout(
            $user,
            $data['billing'],
            $data['same_address'] ? $data['billing'] : ($data['shipping'] ?? null),
        );
    }

    /**
     * @param  array<int, array{name: string, quantity: int, total: BigNumber|string, campaign?: ?string}>  $lines
     * @return array<int, array{name: string, quantity: int, total_formatted: string, campaign: ?string}>
     */
    public static function summaryLines(array $lines, Currency $currency): array
    {
        return array_map(fn (array $l) => [
            'name' => $l['name'],
            'quantity' => $l['quantity'],
            'total_formatted' => $currency->format(Money::decimal($l['total'])),
            'campaign' => $l['campaign'] ?? null,
        ], array_values($lines));
    }

    /**
     * Aktif kargo firmaları ve bu sepet için hesaplanan ücretleri.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public static function carrierOptions($lines, BigNumber|int|string $grossTotal, Currency $currency, ShippingService $shipping): array
    {
        $options = [];

        foreach ($shipping->activeCarriers() as $carrier) {
            /** @var Carrier $carrier */
            try {
                $fee = $shipping->quote($lines, $carrier, $grossTotal, $currency);
            } catch (ShopException) {
                continue;
            }

            $options[] = [
                'id' => $carrier->id,
                'name' => $carrier->name,
                'logo' => $carrier->logoUrl(),
                'delivery_days' => $carrier->delivery_days,
                'is_default' => $carrier->is_default,
                'shipping' => $fee,
                'shipping_formatted' => $fee->isPositive() ? $currency->format($fee) : __('Ücretsiz'),
                'total' => Money::add($grossTotal, $fee),
                'total_formatted' => $currency->format(Money::add($grossTotal, $fee)),
            ];
        }

        return $options;
    }

    /**
     * Ödeme ekranındaki yöntemler (PaymentRegistry): sürücünün koşulu, müşterinin
     * izinli yöntemleri ve yönteme göre en düşük tutar uygulanmış olarak.
     *
     * `adjustment_rate` yüzde (kesin, metin); tutar farkı ön yüzde aynı kuralla (HalfUp, 2 hane) hesaplanır.
     *
     * @return array<int, array{value: string, label: string, description: ?string, icon: string, adjustment_rate: BigDecimal, adjustment_label: ?string}>
     */
    public static function paymentMethods(?User $user = null, BigNumber|int|string $total = 0, ?Currency $currency = null): array
    {
        $context = PaymentContext::for($user, $total, $currency);

        return array_values(array_map(fn (PaymentGateway $g) => [
            'value' => $g->key(),
            'label' => $g->label(),
            'description' => $g->description($context),
            'icon' => $g->icon(),
            'adjustment_rate' => Money::round($g->adjustmentRate($context)),
            'adjustment_label' => $g->adjustmentLabel($context),
            // Taksit tablosu (kart sağlayıcıları): ödeme ekranında bilgi amaçlı.
            'installments_url' => $g instanceof ProvidesInstallments ? route('shop.payment.installments', $g->key()) : null,
        ], app(PaymentRegistry::class)->available($context)));
    }

    /** Ödeme ekranının tek kullanımlık anahtarı (CheckoutGuard). */
    public static function newCheckoutToken(): string
    {
        return (string) Str::uuid();
    }

    public static function ensureMethodAvailable(?string $method, ?User $user = null, BigNumber|int|string $total = 0, ?Currency $currency = null): void
    {
        $available = array_column(self::paymentMethods($user, $total, $currency), 'value');

        if (! in_array((string) $method, $available, true)) {
            throw new ShopException(__('Seçilen ödeme yöntemi şu anda kullanılamıyor.'));
        }
    }

    /**
     * Sipariş sonrası: sürücü bir ödeme sayfası istediyse oraya (kart), yoksa sipariş sayfasına.
     */
    public static function redirectAfterPlaced(Order $order, string $url): RedirectResponse|SymfonyResponse
    {
        $start = $order->paymentStart;

        if ($start?->redirects()) {
            $target = (string) $start->redirectUrl;

            return parse_url($target, PHP_URL_HOST) === null || parse_url($target, PHP_URL_HOST) === request()->getHost()
                ? redirect()->to($target)
                : Inertia::location($target);
        }

        return redirect()->to($url)
            ->with('success', $start?->message ?? __('Siparişiniz alındı. Sipariş numaranız: :number', ['number' => $order->number]));
    }

    /**
     * Ödeme formunda onaylatılan sözleşmeler (Tanımlamalar → Sözleşmeler,
     * "Ödeme formunda göster"). Boşsa onay kutusu da gösterilmez.
     *
     * @return array<int, array{title: string, slug: string, content: string}>
     */
    public static function contracts(): array
    {
        return Contract::forForm(Contract::CHECKOUT);
    }

    /**
     * Giriş yapmış üyenin bilgileriyle formu önden doldurur.
     *
     * @return array<string, mixed>
     */
    public static function prefill(?User $user): array
    {
        $emptyShipping = ['name' => '', 'phone' => '', 'address' => '', 'city' => '', 'district' => '', 'postal_code' => ''];
        $empty = [
            'email' => '',
            'phone' => '',
            'billing' => ['type' => 'individual', 'name' => '', 'company' => '', 'address' => '', 'city' => '', 'district' => '', 'postal_code' => ''],
            'shipping' => $emptyShipping,
        ];

        if ($user === null) {
            return $empty;
        }

        // Adreslerim'deki varsayılan teslimat ve fatura adresleri (ayrı kayıtlar) önceliklidir.
        $saved = $user->account_id !== null
            ? CustomerAddress::query()->where('account_id', $user->account_id)->with(['city:id,name', 'district:id,name'])
                ->orderByDesc('is_default_billing')->orderByDesc('is_default_shipping')->oldest()->get()
            : collect();

        $savedBilling = $saved->firstWhere('type', AddressType::Billing);
        $savedShipping = $saved->firstWhere('type', AddressType::Shipping);

        $shipping = $savedShipping !== null ? [
            'name' => $savedShipping->name,
            'phone' => (string) ($savedShipping->phone ?? ''),
            'address' => $savedShipping->address,
            'city' => (string) ($savedShipping->city?->name ?? ''),
            'district' => (string) ($savedShipping->district?->name ?? ''),
            'postal_code' => (string) ($savedShipping->postal_code ?? ''),
        ] : $emptyShipping;

        if ($savedBilling !== null) {
            return [
                'email' => (string) $user->email,
                'phone' => (string) ($savedBilling->phone ?? $savedShipping?->phone ?? ''),
                'billing' => [
                    'type' => $savedBilling->company ? 'corporate' : 'individual',
                    'name' => $savedBilling->name,
                    'company' => (string) ($savedBilling->company ?? ''),
                    'address' => $savedBilling->address,
                    'city' => (string) ($savedBilling->city?->name ?? ''),
                    'district' => (string) ($savedBilling->district?->name ?? ''),
                    'postal_code' => (string) ($savedBilling->postal_code ?? ''),
                ],
                'shipping' => $shipping,
            ];
        }

        $user->loadMissing(['account.address.city', 'account.address.district']);
        $address = $user->account?->address;

        return [
            'email' => (string) $user->email,
            'phone' => (string) ($savedShipping?->phone ?? ''),
            'billing' => [
                'type' => $user->account?->title ? 'corporate' : 'individual',
                'name' => (string) $user->name,
                'company' => (string) ($user->account?->title ?? ''),
                'address' => (string) ($address?->address ?? ''),
                'city' => (string) ($address?->city?->name ?? ''),
                'district' => (string) ($address?->district?->name ?? ''),
                'postal_code' => (string) ($address?->postal_code ?? ''),
            ],
            'shipping' => $shipping,
        ];
    }
}
