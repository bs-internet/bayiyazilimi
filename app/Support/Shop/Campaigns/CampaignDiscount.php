<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use Brick\Math\BigDecimal;

/**
 * Bir sepet satırına uygulanan kampanya indirimi (KDV hariç, sepet para biriminde).
 */
final readonly class CampaignDiscount
{
    public function __construct(
        public string $productId,
        public BigDecimal $amount,
        public string $label,
        public string $campaignKey,
    ) {}
}
