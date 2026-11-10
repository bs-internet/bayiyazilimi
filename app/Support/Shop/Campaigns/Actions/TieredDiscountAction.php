<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Actions;

use App\Models\Campaign;
use App\Services\Shop\CurrencyManager;
use App\Support\Money;
use App\Support\Shop\Campaigns\CampaignAction;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\CampaignOutcome;
use Brick\Math\BigDecimal;

/**
 * Kademeli yüzde indirim. Basamaklar en az değer → oran listesidir; ulaşılan
 * en yüksek basamağın oranı uygulanır:
 * - `line_quantity`: her satır kendi adediyle (ör. 3 adet %5, 10 adet %10),
 * - `cart_total`: kampanya öncesi sepet tutarına (KDV dahil, kampanyanın para
 *   biriminde girilir) göre koşula uyan bütün satırlara (ör. 1.000 TL üstü %5).
 */
final class TieredDiscountAction implements CampaignAction
{
    public const LINE = 'line_quantity';

    public const CART = 'cart_total';

    public function key(): string
    {
        return 'tiered';
    }

    public function label(): string
    {
        return __('Kademeli indirim');
    }

    public function fields(): array
    {
        return [
            ['key' => 'basis', 'label' => __('Kademe ölçütü'), 'type' => 'select', 'options' => [
                ['value' => self::LINE, 'label' => __('Ürün adedi (her ürün ayrı)')],
                ['value' => self::CART, 'label' => __('Sepet tutarı (KDV dahil)')],
            ]],
            ['key' => 'tiers', 'label' => __('Kademeler'), 'type' => 'tiers', 'help' => __('Ulaşılan en yüksek kademenin oranı uygulanır.')],
        ];
    }

    public function rules(): array
    {
        return [
            'settings.basis' => ['required', 'in:'.self::LINE.','.self::CART],
            'settings.tiers' => ['required', 'array', 'min:1', 'max:10'],
            'settings.tiers.*.min' => ['required', 'numeric', 'gt:0'],
            'settings.tiers.*.rate' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function summary(array $settings): string
    {
        $cart = ($settings['basis'] ?? self::LINE) === self::CART;
        $currency = Campaign::moneyCurrency($settings['currency'] ?? null);

        $parts = array_map(fn (array $tier) => $cart
            ? __(':min üstü %:rate', ['min' => $currency->format($tier['min']), 'rate' => $this->number($tier['rate'])])
            : __(':min adet %:rate', ['min' => $this->number($tier['min']), 'rate' => $this->number($tier['rate'])]),
            $this->tiers($settings));

        return $parts === [] ? $this->label() : implode(', ', $parts);
    }

    public function badge(array $settings): string
    {
        return __('Kademeli İndirim');
    }

    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome
    {
        $tiers = $this->tiers($settings);

        if ($tiers === []) {
            return new CampaignOutcome;
        }

        $discounts = [];

        if (($settings['basis'] ?? self::LINE) === self::CART) {
            $total = app(CurrencyManager::class)->convert($context->cartGross(), $context->currency, Campaign::moneyCurrency($settings['currency'] ?? null));
            $rate = $this->rateFor($tiers, $total);

            if ($rate !== null) {
                foreach ($lines as $line) {
                    $discounts[$line['product']->id] = Money::percent(Money::times($line['unit_net'], $line['quantity'], 4), $rate);
                }
            }

            return new CampaignOutcome(discounts: $discounts);
        }

        foreach ($lines as $line) {
            $rate = $this->rateFor($tiers, Money::decimal($line['quantity']));

            if ($rate !== null) {
                $discounts[$line['product']->id] = Money::percent(Money::times($line['unit_net'], $line['quantity'], 4), $rate);
            }
        }

        return new CampaignOutcome(discounts: $discounts);
    }

    /**
     * Geçerli basamaklar, en az değere göre artan.
     *
     * @param  array<string, mixed>  $settings
     * @return list<array{min: BigDecimal, rate: BigDecimal}>
     */
    private function tiers(array $settings): array
    {
        $tiers = [];

        foreach ((array) ($settings['tiers'] ?? []) as $tier) {
            if (! is_array($tier) || ! is_numeric($tier['min'] ?? null) || ! is_numeric($tier['rate'] ?? null)) {
                continue;
            }

            $min = Money::decimal((string) $tier['min']);
            $rate = Money::min(100, Money::max(0, Money::decimal((string) $tier['rate'])));

            if ($min->isPositive() && $rate->isPositive()) {
                $tiers[] = ['min' => $min, 'rate' => $rate];
            }
        }

        usort($tiers, fn (array $a, array $b) => $a['min']->compareTo($b['min']));

        return $tiers;
    }

    /**
     * Ulaşılan en yüksek basamağın oranı; hiçbirine ulaşılmadıysa null.
     *
     * @param  list<array{min: BigDecimal, rate: BigDecimal}>  $tiers
     */
    private function rateFor(array $tiers, BigDecimal $value): ?BigDecimal
    {
        $rate = null;

        foreach ($tiers as $tier) {
            if ($value->isGreaterThanOrEqualTo($tier['min'])) {
                $rate = $tier['rate'];
            }
        }

        return $rate;
    }

    private function number(BigDecimal $value): string
    {
        return str_replace('.', ',', (string) $value->strippedOfTrailingZeros());
    }
}
