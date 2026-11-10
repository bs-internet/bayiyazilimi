<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;

/**
 * Ürün fiyatlarını vitrin para birimine çevirir, indirimleri uygular ve KDV ekler.
 *
 * Ürün fiyatları KDV hariç ve ürünün kendi para biriminde saklanır.
 *
 * İndirim önceliği (birikmez, ilk uygulanan geçerlidir):
 * 1. Ürün indirimi — karşılaştırma fiyatı (compare_price) satış fiyatından yüksekse
 * 2. Bayi indirimi — giriş yapmış bayinin özel oranı, yoksa bayi grubunun oranı
 *
 * Yani ürüne indirim girildiğinde bayi indirimi uygulanmaz.
 */
class PriceCalculator
{
    private ?BigDecimal $dealerRate = null;

    public function __construct(
        private readonly CurrencyManager $currencies,
    ) {}

    /**
     * KDV hariç birim fiyat (indirim uygulanmış, hedef para biriminde).
     */
    public function net(Product $product, ?Currency $target = null): BigDecimal
    {
        $target ??= $this->currencies->current();

        return Money::round($this->currencies->convert($this->unitPrice($product), $product->currency, $target), 4);
    }

    /**
     * KDV dahil birim fiyat (hedef para biriminde).
     */
    public function gross(Product $product, ?Currency $target = null): BigDecimal
    {
        return Money::withRate($this->net($product, $target), $product->tax_rate);
    }

    /**
     * KDV dahil karşılaştırma (üstü çizili) fiyat; indirim yoksa null.
     */
    public function compareGross(Product $product, ?Currency $target = null): ?BigDecimal
    {
        $compare = $this->compareNet($product, $target);

        return $compare === null ? null : Money::withRate($compare, $product->tax_rate);
    }

    /**
     * KDV hariç karşılaştırma (üstü çizili) fiyat; indirim yoksa null.
     */
    public function compareNet(Product $product, ?Currency $target = null): ?BigDecimal
    {
        $discount = $this->discount($product);

        if ($discount['source'] === null) {
            return null;
        }

        $target ??= $this->currencies->current();

        return Money::round($this->currencies->convert($discount['list_price'], $product->currency, $target), 4);
    }

    /**
     * Vitrinde gösterilecek birim fiyat: Site Ayarları'ndaki gösterim tercihine
     * göre KDV dahil ya da hariç (bayiler ve diğer ziyaretçiler için ayrı ayar).
     */
    public function display(Product $product, ?Currency $target = null): BigDecimal
    {
        return $this->displaysGross() ? $this->gross($product, $target) : Money::round($this->net($product, $target));
    }

    public function compareDisplay(Product $product, ?Currency $target = null): ?BigDecimal
    {
        if ($this->displaysGross()) {
            return $this->compareGross($product, $target);
        }

        $net = $this->compareNet($product, $target);

        return $net === null ? null : Money::round($net);
    }

    public function formatDisplay(Product $product, ?Currency $target = null): string
    {
        $target ??= $this->currencies->current();

        return $target->format($this->display($product, $target));
    }

    /**
     * Fiyatlar KDV dahil mi gösteriliyor? (giriş yapan bayiye ayrı ayar)
     */
    public function displaysGross(): bool
    {
        $key = $this->viewerIsDealer() ? 'dealer_price_display' : 'price_display';

        return (ShopSettings::get($key) ?: 'gross') !== 'net';
    }

    /**
     * Uygulanan indirim: yüzde, kaynak (product|dealer|null) ve
     * indirimsiz liste fiyatı (ürünün kendi para biriminde, KDV hariç).
     *
     * @return array{rate: int, source: ?string, list_price: BigDecimal}
     */
    public function discount(Product $product): array
    {
        $price = $product->price;

        if (! $price->isPositive()) {
            return ['rate' => 0, 'source' => null, 'list_price' => $price];
        }

        if ($product->discountRate() > 0) {
            return ['rate' => $product->discountRate(), 'source' => 'product', 'list_price' => $product->compare_price ?? $price];
        }

        $dealerRate = $this->dealerRate();

        if ($dealerRate->isPositive()) {
            // Gösterim yüzdesi (tam sayı); hesap dealerRate() ile kesin yapılır.
            return ['rate' => $dealerRate->toScale(0, RoundingMode::HalfUp)->toInt(), 'source' => 'dealer', 'list_price' => $price];
        }

        return ['rate' => 0, 'source' => null, 'list_price' => $price];
    }

    /**
     * İndirim uygulanmış KDV hariç birim fiyat (ürünün kendi para biriminde).
     */
    public function unitPrice(Product $product): BigDecimal
    {
        $price = $product->price;

        if (! $price->isPositive()) {
            return $price;
        }

        // Ürün indirimi zaten satış fiyatına yansımıştır (compare_price > price).
        if ($product->discountRate() > 0) {
            return $price;
        }

        $rate = $this->dealerRate();

        return $rate->isPositive() ? Money::percent($price, Money::sub(100, $rate), 4) : $price;
    }

    /**
     * Fiyatı görüntüleyen kullanıcı bayi mi?
     */
    public function viewerIsDealer(): bool
    {
        $user = $this->viewer();

        return $user !== null && $user->isDealer();
    }

    /**
     * Giriş yapmış bayinin indirim oranı (istek boyunca bir kez hesaplanır).
     */
    public function dealerRate(): BigDecimal
    {
        if ($this->dealerRate !== null) {
            return $this->dealerRate;
        }

        $user = $this->viewer();

        if ($user === null || ! $user->user_type->isMember() || $user->account_id === null) {
            return $this->dealerRate = Money::round(0);
        }

        $user->loadMissing('account.dealerGroup');

        return $this->dealerRate = $user->account?->dealerDiscountRate() ?? Money::round(0);
    }

    private function viewer(): ?User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->account_id !== null) {
            $user->loadMissing('account.dealerGroup');
        }

        return $user;
    }
}
