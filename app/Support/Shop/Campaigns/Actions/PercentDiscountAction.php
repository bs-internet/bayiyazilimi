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
 * Koşula uyan satırlarda yüzde indirim. İsteğe bağlı üst sınır (KDV hariç,
 * kampanyanın para biriminde): toplam indirim sınırı aşarsa sınır, satırlara
 * indirimleri oranında kuruşu kuruşuna dağıtılır.
 */
final class PercentDiscountAction implements CampaignAction
{
    public function key(): string
    {
        return 'percent';
    }

    public function label(): string
    {
        return __('Yüzde indirim');
    }

    public function fields(): array
    {
        return [
            ['key' => 'rate', 'label' => __('İndirim oranı'), 'type' => 'number', 'suffix' => '%'],
            ['key' => 'max_amount', 'label' => __('En fazla indirim (KDV hariç)'), 'type' => 'money', 'help' => __('Boş bırakılırsa sınır yok.')],
        ];
    }

    public function rules(): array
    {
        return [
            'settings.rate' => ['required', 'numeric', 'gt:0', 'max:100'],
            'settings.max_amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function summary(array $settings): string
    {
        $cap = $this->cap($settings);

        return $cap === null
            ? __('%:rate indirim', ['rate' => $this->rate($settings)])
            : __('%:rate indirim (en fazla :amount)', [
                'rate' => $this->rate($settings),
                'amount' => Campaign::moneyCurrency($settings['currency'] ?? null)->format($cap),
            ]);
    }

    public function badge(array $settings): ?string
    {
        return __('%:rate İndirim', ['rate' => $this->rate($settings)]);
    }

    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome
    {
        $rate = Money::min(100, Money::max(0, Money::decimal($settings['rate'] ?? 0)));
        $discounts = [];

        foreach ($lines as $line) {
            $discounts[$line['product']->id] = Money::percent(Money::times($line['unit_net'], $line['quantity'], 4), $rate);
        }

        $cap = $this->cap($settings);

        if ($cap !== null && $discounts !== []) {
            $cap = Money::round(app(CurrencyManager::class)->convert($cap, Campaign::moneyCurrency($settings['currency'] ?? null), $context->currency));

            if (Money::add(...array_values($discounts))->isGreaterThan($cap)) {
                $discounts = Money::allocate($cap, $discounts);
            }
        }

        return new CampaignOutcome(discounts: $discounts);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function cap(array $settings): ?BigDecimal
    {
        $value = $settings['max_amount'] ?? null;

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $cap = Money::round((string) $value);

        return $cap->isPositive() ? $cap : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function rate(array $settings): string
    {
        $rate = Money::decimal(is_numeric($settings['rate'] ?? null) ? (string) $settings['rate'] : 0)->strippedOfTrailingZeros();

        return str_replace('.', ',', (string) $rate);
    }
}
