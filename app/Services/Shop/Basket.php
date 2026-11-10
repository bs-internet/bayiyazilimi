<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Exceptions\Shop\ShopException;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\CampaignDiscount;
use App\Support\Shop\Campaigns\CampaignEngine;
use App\Support\Shop\Campaigns\CampaignRegistry;
use App\Support\Shop\Campaigns\CampaignResult;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Oturumda tutulan ürün listesi. İki örneği vardır:
 * - Sepet (`cart`)  → doğrudan satış, ödeme ile siparişe döner.
 * - Teklif listesi (`quote`) → teklif talebi olarak gönderilir.
 *
 * Oturumda yalnızca `product_id => quantity` saklanır; fiyatlar her
 * okumada güncel ürün/kur bilgisinden hesaplanır.
 */
class Basket
{
    private ?CampaignResult $campaign = null;

    private ?CampaignContext $campaignContext = null;

    /** @var array{email: ?string, payment_method: ?string, city: ?string} ödeme adımında bilinenler */
    private array $checkout = ['email' => null, 'payment_method' => null, 'city' => null];

    public function __construct(
        private readonly string $name,
        private readonly PriceCalculator $prices,
        private readonly CurrencyManager $currencies,
    ) {}

    public static function cart(): self
    {
        return app()->makeWith(self::class, ['name' => 'cart']);
    }

    public static function quote(): self
    {
        return app()->makeWith(self::class, ['name' => 'quote']);
    }

    /**
     * Kayıtlı sepetten doğrudan sipariş için geçici sepet: bayinin asıl
     * sepetine dokunmadan ödeme ekranı bu sepetle çalışır. Her kayıtlı sepetin
     * kendi sipariş sepeti vardır: iki sekmede iki kayıtlı sepet açılsa da sipariş
     * doğru sepetin ürünleriyle verilir.
     */
    public static function savedCheckout(string $savedCartId): self
    {
        return app()->makeWith(self::class, ['name' => 'saved.'.$savedCartId]);
    }

    private function key(): string
    {
        return 'shop.basket.'.$this->name;
    }

    /**
     * @return array<string, int>
     */
    public function raw(): array
    {
        return session($this->key(), []);
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    public function quantityOf(Product $product): int
    {
        return $this->raw()[$product->id] ?? 0;
    }

    public function add(Product $product, int $quantity): void
    {
        $items = $this->raw();
        $items[$product->id] = ($items[$product->id] ?? 0) + max(1, $quantity);
        session([$this->key() => $items]);
    }

    public function set(Product $product, int $quantity): void
    {
        $items = $this->raw();

        if ($quantity <= 0) {
            unset($items[$product->id]);
        } else {
            $items[$product->id] = $quantity;
        }

        session([$this->key() => $items]);
    }

    public function remove(string $productId): void
    {
        $items = $this->raw();
        unset($items[$productId]);
        session([$this->key() => $items]);
    }

    public function clear(): void
    {
        session()->forget($this->key());
    }

    /**
     * Tüm ürünleri hedef listeye ekleyip bu listeyi boşaltır (sepet → teklif listesi).
     * Taşınan ürün çeşidi sayısını döner.
     */
    public function moveTo(self $target): int
    {
        $items = $this->raw();
        $merged = $target->raw();

        foreach ($items as $id => $qty) {
            $merged[$id] = ($merged[$id] ?? 0) + $qty;
        }

        session([$target->key() => $merged]);
        $this->clear();

        return count($items);
    }

    /**
     * Satırlar: ürün, adet, KDV hariç/dahil birim ve satır tutarları (hedef para biriminde).
     *
     * Kampanya hediyeleri `gift` = true satırlardır.
     *
     * Tüm tutarlar kesin ondalık (BigDecimal; JSON'da metin): birim fiyat 4, satır / KDV 2 hane.
     *
     * @return Collection<int, array{product: Product, quantity: int, unit_net: BigDecimal, unit_gross: BigDecimal, tax_rate: BigDecimal, gift: bool, discount: BigDecimal, campaign: ?string, campaign_shipping: ?BigDecimal, line_net: BigDecimal, line_tax: BigDecimal, line_gross: BigDecimal}>
     */
    public function lines(?Currency $currency = null): Collection
    {
        $currency ??= $this->currencies->current();
        $raw = $this->raw();

        if ($raw === []) {
            return collect();
        }

        $products = Product::query()
            ->visible()
            ->with(['currency', 'images', 'brand'])
            ->whereIn('id', array_keys($raw))
            ->get()
            ->keyBy('id');

        // Silinmiş / pasifleşmiş ürünleri oturumdan temizle.
        $missing = array_diff(array_keys($raw), $products->keys()->all());
        foreach ($missing as $id) {
            $this->remove($id);
        }

        $lines = collect($raw)
            ->filter(fn ($qty, $id) => $products->has($id))
            ->map(fn (int $qty, string $id) => [
                'product' => $products[$id],
                'quantity' => $qty,
                'unit_net' => $this->prices->net($products[$id], $currency),
                'tax_rate' => $products[$id]->tax_rate,
            ])
            ->values();

        // Kampanyalar yalnızca sepete uygulanır; teklif listesine değil. Sepette en çok
        // kazandıran kampanya (ya da birleşebilen kampanyaların birleşimi) geçerlidir.
        // (Kayıtlı sepetten sipariş sepeti de kampanya alır.)
        $this->campaign = $this->name !== 'quote' ? $this->evaluateCampaign($lines, $currency) : null;
        $campaign = $this->campaign;

        // Hediye ürünler 0 tutarlı ayrı satır olarak eklenir (siparişe geçer, stoktan düşer).
        foreach ($campaign->gifts ?? [] as $gift) {
            $lines->push([
                'product' => $gift['product'],
                'quantity' => $gift['quantity'],
                'unit_net' => $this->prices->net($gift['product'], $currency),
                'tax_rate' => $gift['product']->tax_rate,
                'gift' => true,
            ]);
        }

        return $lines->map(function (array $line) use ($campaign) {
            $unitNet = $line['unit_net'];
            $taxRate = $line['tax_rate'];
            $gift = $line['gift'] ?? false;
            $discount = $gift ? null : ($campaign->discounts[$line['product']->id] ?? null);
            $discountNet = $gift ? Money::times($unitNet, $line['quantity']) : Money::round($discount?->amount ?? 0);
            $amounts = Money::line($unitNet, $line['quantity'], $taxRate, $discountNet);
            $lineNet = $amounts['net'];
            $lineTax = $amounts['tax'];

            return [
                ...$line,
                'gift' => $gift,
                'unit_gross' => Money::withRate($unitNet, $taxRate),
                'discount' => $discountNet,
                'campaign' => $this->lineCampaign($gift, $discount, $campaign),
                // Kampanyanın kargo ücreti (ücretsiz / sabit; null = sepete göre), bkz. ShippingService::quote.
                'campaign_shipping' => $campaign?->shipping,
                'line_net' => $lineNet,
                'line_tax' => $lineTax,
                'line_gross' => $amounts['gross'],
            ];
        });
    }

    /**
     * Satırın kampanya etiketi: indirimi veren kampanya(lar); hediye satırında uygulanan kampanya(lar).
     */
    private function lineCampaign(bool $gift, ?CampaignDiscount $discount, ?CampaignResult $campaign): ?string
    {
        if ($discount !== null) {
            return $discount->label;
        }

        return $gift ? $campaign?->name() : null;
    }

    /**
     * Ödeme adımında bilinenler kampanyalara verilir (ödeme yöntemi / il koşulları,
     * misafirin müşteri başına limiti). Sonraki `lines()` / `totals()` çağrılarında geçerlidir.
     */
    public function useCheckout(?string $email, ?string $paymentMethod, ?string $city): self
    {
        $this->checkout = ['email' => $email ?: null, 'payment_method' => $paymentMethod ?: null, 'city' => $city ?: null];

        return $this;
    }

    /**
     * Bayinin varsayılan teslimat adresinin ili (il koşullu kampanyalar sepet ekranında da görünsün).
     */
    private function defaultCity(?User $user): ?string
    {
        if ($user?->account_id === null) {
            return null;
        }

        return CustomerAddress::query()->withoutGlobalScopes()
            ->where('account_id', $user->account_id)
            ->where('is_default_shipping', true)
            ->with('city:id,name')
            ->first()?->city?->name;
    }

    /**
     * Son kampanya değerlendirmesinin bağlamı (sipariş verilirken limit yeniden denetlenir).
     */
    public function campaignContext(): ?CampaignContext
    {
        return $this->campaignContext;
    }

    /**
     * Son `lines()` çağrısında sepete uygulanan kampanya.
     */
    public function campaign(): ?CampaignResult
    {
        return $this->campaign;
    }

    /**
     * @param  Collection<int, array{product: Product, quantity: int, unit_net: BigDecimal, tax_rate: BigDecimal}>  $lines
     */
    private function evaluateCampaign(Collection $lines, Currency $currency): ?CampaignResult
    {
        $user = Auth::user();
        $context = new CampaignContext(
            lines: $lines->all(),
            currency: $currency,
            viewer: $user instanceof User ? $user : null,
            viewerIsDealer: $this->prices->viewerIsDealer(),
            email: $this->checkout['email'] ?? ($user instanceof User ? $user->email : null),
            paymentMethod: $this->checkout['payment_method'],
            city: $this->checkout['city'] ?? $this->defaultCity($user instanceof User ? $user : null),
            // Modüllerin kampanya koşulları için ek bilgiler (ör. vitrin kupon kodu).
            attributes: (array) hooks()->apply('shop.campaigns.context', [], basket: $this->name),
        );
        $this->campaignContext = $context;

        // Ücretsiz kargonun değeri: kampanyasız, varsayılan firmayla kargo ücreti.
        $shippingEstimate = function () use ($lines, $currency, $context): BigDecimal {
            $shipping = app(ShippingService::class);

            try {
                return $shipping->quote($lines, $shipping->defaultCarrier(), $context->cartGross(), $currency);
            } catch (ShopException) {
                return Money::round(0);
            }
        };

        return app(CampaignEngine::class)->evaluate($context, $shippingEstimate);
    }

    /**
     * Tutarlar. Kargo, verilen (yoksa varsayılan) kargo firmasının desi tablosundan hesaplanır.
     *
     * @return array{discount: BigDecimal, subtotal: BigDecimal, tax: BigDecimal, shipping: BigDecimal, total: BigDecimal, desi: BigDecimal, carrier: ?Carrier, currency: Currency, campaign: array{name: string, summary: string, shipping: ?string, others: int}|null, nudge: array{amount: BigDecimal, name: string, summary: string}|null}
     */
    public function totals(?Currency $currency = null, ?Carrier $carrier = null): array
    {
        $currency ??= $this->currencies->current();
        $lines = $this->lines($currency);
        $shippingService = app(ShippingService::class);
        $carrier ??= $shippingService->defaultCarrier();

        $subtotal = Money::add(...$lines->pluck('line_net'));
        $tax = Money::add(...$lines->pluck('line_tax'));
        $shipping = $shippingService->quote($lines, $carrier, Money::add($subtotal, $tax), $currency);

        return [
            // Ara toplam kampanya indirimi düşülmüş tutardır; indirim ayrıca bilgi olarak verilir.
            'discount' => Money::add(...$lines->pluck('discount')),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => Money::add($subtotal, $tax, $shipping),
            'desi' => $shippingService->totalDesi($lines),
            'carrier' => $carrier,
            'currency' => $currency,
            // Uygulanan kampanya(lar) ve uyan ama uygulanmayanların sayısı.
            'campaign' => $this->campaign !== null ? [
                'name' => $this->campaign->name(),
                'summary' => $this->campaign->summary,
                'shipping' => $this->campaign->shippingCampaign !== null ? app(CampaignRegistry::class)->shippingText($this->campaign->shippingCampaign) : null,
                'others' => $this->campaign->others,
            ] : null,
            // "Şu kadar daha ekleyin" teşviki (yalnız sepet tutarı eksik kalan kampanya).
            'nudge' => $this->campaignContext !== null ? app(CampaignEngine::class)->nudge($this->campaignContext, $this->campaign) : null,
        ];
    }
}
