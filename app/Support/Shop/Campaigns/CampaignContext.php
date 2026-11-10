<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;

/**
 * Kampanya koşullarına ve işlemlerine verilen sepet görüntüsü. Birim fiyatlar
 * diğer indirimler (ürün, bayi) uygulanmış KDV hariç tutarlardır.
 *
 * `preview` (ürün kartı etiketi): sepet yoktur; sepete bağlı koşullar
 * (tutar, adet) sağlanmış sayılır.
 *
 * Ödeme adımında bilinenler (e-posta, ödeme yöntemi, il): sepet ekranında
 * boştur; ödeme yöntemi / il koşulları ve misafirin müşteri başına limiti ödemede denetlenir.
 *
 * `attributes`: modüllerin koşulları için ek bilgiler (`shop.campaigns.context`
 * filtresiyle; ör. vitrinin kupon kodu). Çekirdek içeriğini bilmez.
 */
final readonly class CampaignContext
{
    /**
     * @param  array<int, array{product: Product, quantity: int, unit_net: BigDecimal, tax_rate: BigDecimal}>  $lines
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $lines,
        public Currency $currency,
        public ?User $viewer,
        public bool $viewerIsDealer,
        public bool $preview = false,
        public ?string $email = null,
        public ?string $paymentMethod = null,
        public ?string $city = null,
        public array $attributes = [],
    ) {}

    /**
     * Kampanya öncesi sepet tutarı (KDV dahil, sepet para biriminde).
     */
    public function cartGross(): BigDecimal
    {
        return Money::add(...array_map(
            fn (array $l) => Money::withRate(Money::times($l['unit_net'], $l['quantity'], 4), $l['tax_rate'], 4),
            $this->lines,
        ));
    }

    public function cartQuantity(): int
    {
        return (int) array_sum(array_column($this->lines, 'quantity'));
    }
}
