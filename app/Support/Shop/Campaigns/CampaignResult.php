<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Models\Campaign;
use App\Models\Product;
use Brick\Math\BigDecimal;

/**
 * Sepete uygulanan kampanya(lar) ve etkisi. `campaign` birleşimin öndeki
 * (en çok kazandıran) kampanyasıdır; `campaigns` birlikte uygulananların tümü.
 */
final readonly class CampaignResult
{
    /**
     * @param  array<string, CampaignDiscount>  $discounts  ürün id => indirim (satır tutarıyla sınırlı)
     * @param  BigDecimal|null  $shipping  kampanya kargo ücreti (KDV dahil, sepet para biriminde); null = sepete göre
     * @param  array<int, array{product: Product, quantity: int}>  $gifts
     * @param  int  $others  sepete uyan ama uygulanmayan kampanya sayısı
     * @param  array<int, Campaign>  $campaigns  uygulanan kampanyalar (ilki `campaign`)
     * @param  Campaign|null  $shippingCampaign  kargosu uygulanan kampanya
     * @param  array<string, BigDecimal>  $values  kampanya id => tek başına kazancı (sepet para biriminde; limit / bütçe kaydı)
     */
    public function __construct(
        public Campaign $campaign,
        public string $summary,
        public array $discounts,
        public ?BigDecimal $shipping,
        public array $gifts,
        public BigDecimal $value,
        public int $others,
        public array $campaigns = [],
        public ?Campaign $shippingCampaign = null,
        public array $values = [],
    ) {}

    /**
     * Uygulanan kampanyaların adları ("A + B").
     */
    public function name(): string
    {
        return implode(' + ', array_map(fn (Campaign $c) => $c->name, $this->campaigns ?: [$this->campaign]));
    }
}
