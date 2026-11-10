<?php

declare(strict_types=1);

namespace App\Http\Resources\Shop;

use App\Models\Carrier;
use App\Models\Currency;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Sepet / teklif listesi satırlarını ve tutarlarını vitrin için biçimlendirir.
 */
final class BasketPresenter
{
    /**
     * @param  Collection<int, array<string, mixed>>  $lines  Basket::lines()
     * @return array<int, array<string, mixed>>
     */
    public static function lines(Collection $lines, Currency $currency): array
    {
        return $lines->map(function (array $l) use ($currency) {
            $product = $l['product'];
            $hasPrice = $product->showsPrice();

            $gift = (bool) ($l['gift'] ?? false);

            return [
                'id' => $product->id,
                // Aynı ürün hem satır hem hediye olabilir: liste anahtarı.
                'key' => $gift ? "gift-{$product->id}" : $product->id,
                // Kampanya hediyesi: adet değiştirilemez, kaldırılamaz.
                'gift' => $gift,
                'slug' => $product->slug,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand?->name,
                'image' => $product->coverUrl(),
                'quantity' => $l['quantity'],
                'min_qty' => $product->min_qty,
                'max_qty' => $product->max_qty,
                'has_price' => $hasPrice,
                'purchasable' => $product->sale_mode->allowsCart() && $hasPrice,
                'in_stock' => $product->hasStockFor($l['quantity']),
                'stock' => $product->stock,
                'unit_gross_formatted' => $currency->format($l['unit_gross']),
                'campaign' => $l['campaign'] ?? null,
                'discount' => $l['discount'] ?? Money::round(0),
                'discount_formatted' => Money::decimal($l['discount'] ?? 0)->isPositive() ? $currency->format(Money::withRate($l['discount'], $l['tax_rate'])) : null,
                'line_gross' => $l['line_gross'],
                'line_gross_formatted' => $currency->format($l['line_gross']),
            ];
        })->values()->all();
    }

    /**
     * @param  array{discount?: BigDecimal, subtotal: BigDecimal, tax: BigDecimal, shipping: BigDecimal, total: BigDecimal, desi?: BigDecimal, carrier?: ?Carrier, campaign?: array<string, mixed>|null, nudge?: array{amount: BigDecimal, name: string, summary: string}|null}  $totals
     * @return array<string, mixed>
     */
    public static function totals(array $totals, Currency $currency): array
    {
        $carrier = $totals['carrier'] ?? null;

        return [
            'discount' => $totals['discount'] ?? Money::round(0),
            'discount_formatted' => Money::decimal($totals['discount'] ?? 0)->isPositive() ? $currency->format($totals['discount']) : null,
            'subtotal' => $totals['subtotal'],
            'subtotal_formatted' => $currency->format($totals['subtotal']),
            'tax' => $totals['tax'],
            'tax_formatted' => $currency->format($totals['tax']),
            'shipping' => $totals['shipping'],
            'shipping_formatted' => $totals['shipping']->isPositive() ? $currency->format($totals['shipping']) : __('Ücretsiz'),
            'total' => $totals['total'],
            'total_formatted' => $currency->format($totals['total']),
            'desi' => $totals['desi'] ?? null,
            'carrier' => $carrier?->name,
            'currency' => $currency->code,
            // Sepette tek kampanya: uygulanan ve uyan ama uygulanmayanların sayısı.
            'campaign' => $totals['campaign'] ?? null,
            'nudge' => isset($totals['nudge']) ? [
                'amount' => $totals['nudge']['amount'],
                'amount_formatted' => $currency->format($totals['nudge']['amount']),
                'name' => $totals['nudge']['name'],
                'summary' => $totals['nudge']['summary'],
            ] : null,
        ];
    }
}
