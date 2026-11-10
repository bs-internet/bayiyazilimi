<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Models\Product;
use Brick\Math\BigDecimal;

/**
 * Kampanya işlemi türü (yüzde indirim, X Al Y Öde, ücretsiz kargo …).
 * CampaignRegistry'ye `shop.campaigns.actions.register` hook'u ile eklenir.
 */
interface CampaignAction
{
    public function key(): string;

    public function label(): string;

    /**
     * Paneldeki ayar alanları; `type`: number | money (kampanya para biriminde) | product | select.
     * `apply` / `summary` ayarlarında `currency` (kampanyanın para birimi kodu, null = varsayılan) bulunur.
     *
     * @return array<int, array{key: string, label: string, type: string, options?: array<int, array{value: string, label: string}>, suffix?: string, help?: string}>
     */
    public function fields(): array;

    /**
     * `settings.*` doğrulama kuralları.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Listede gösterilen kısa özet (ör. "2 Al 1 Öde", "%10 indirim").
     *
     * @param  array<string, mixed>  $settings
     */
    public function summary(array $settings): string;

    /**
     * Ürün kartındaki varsayılan etiket (kampanyada etiket girilmemişse).
     *
     * @param  array<string, mixed>  $settings
     */
    public function badge(array $settings): ?string;

    /**
     * Koşullara uyan sepet satırlarına işlemi uygular.
     *
     * @param  array<int, array{product: Product, quantity: int, unit_net: BigDecimal, tax_rate: BigDecimal}>  $lines
     * @param  array<string, mixed>  $settings
     */
    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome;
}
