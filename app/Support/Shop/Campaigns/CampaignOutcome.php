<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use Brick\Math\BigDecimal;

/**
 * Bir kampanya işleminin sepete etkisi: satır indirimleri (KDV hariç, sepet
 * para biriminde) ve hediye ürünler. Kargo kampanyada ayrıca belirlenir.
 */
final readonly class CampaignOutcome
{
    /**
     * @param  array<string, BigDecimal>  $discounts  ürün id => indirim
     * @param  array<int, array{product_id: string, quantity: int}>  $gifts
     */
    public function __construct(
        public array $discounts = [],
        public array $gifts = [],
    ) {}
}
