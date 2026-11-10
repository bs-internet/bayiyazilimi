<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Models\Product;
use Brick\Math\BigDecimal;

/**
 * Kampanya koşulu türü (ACF'deki "alan"): panelde [alan] [mantık] [değer]
 * satırı olarak seçilir. Koşul türleri CampaignRegistry'ye
 * `shop.campaigns.conditions.register` hook'u ile eklenir.
 *
 * - scope `audience`: "Kime / ne zaman" — kampanyanın sepete uygulanıp uygulanmayacağı.
 * - scope `product`: "Hangi ürünlere" — indirimin hangi sepet satırlarına uygulanacağı.
 */
interface CampaignCondition
{
    public const AUDIENCE = 'audience';

    public const PRODUCT = 'product';

    public function key(): string;

    public function label(): string;

    public function scope(): string;

    /**
     * @return array<string, string> mantık anahtarı => etiket (bkz. Operators)
     */
    public function operators(): array;

    /**
     * Değer girişi: `select` (tek seçim), `multi` (çoklu seçim), `number`, `products` (ürün seçici), `text` (serbest metin; ör. kupon kodu).
     */
    public function input(): string;

    /**
     * Seçim listesi (yalnız panelde istenir).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array;

    /**
     * Sayısal değerin birimi (ör. "₺", "adet").
     */
    public function unit(): ?string;

    /**
     * @param  array{product: Product, quantity: int, unit_net: BigDecimal, tax_rate: BigDecimal}|null  $line  ürün koşullarında satır
     */
    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool;
}
