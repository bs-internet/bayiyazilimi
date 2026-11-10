<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Actions;

use App\Models\Campaign;
use App\Services\Shop\CurrencyManager;
use App\Support\Money;
use App\Support\Shop\Campaigns\CampaignAction;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\CampaignOutcome;

/**
 * Tutar indirimi (KDV hariç, kampanyanın para biriminde girilir; sepet başka
 * para birimindeyse kurla çevrilir):
 * - `unit`: koşula uyan her adet için,
 * - `cart`: sepete bir kez; koşula uyan satırlara tutarlarıyla orantılı dağıtılır.
 */
final class AmountDiscountAction implements CampaignAction
{
    public function key(): string
    {
        return 'amount';
    }

    public function label(): string
    {
        return __('Tutar indirimi');
    }

    public function fields(): array
    {
        return [
            ['key' => 'amount', 'label' => __('İndirim tutarı (KDV hariç)'), 'type' => 'money'],
            ['key' => 'mode', 'label' => __('Uygulama'), 'type' => 'select', 'options' => [
                ['value' => 'cart', 'label' => __('Sepete bir kez')],
                ['value' => 'unit', 'label' => __('Her ürün adedine')],
            ]],
        ];
    }

    public function rules(): array
    {
        return [
            'settings.amount' => ['required', 'numeric', 'gt:0'],
            'settings.mode' => ['required', 'in:cart,unit'],
        ];
    }

    public function summary(array $settings): string
    {
        $amount = Campaign::moneyCurrency($settings['currency'] ?? null)->format(Money::round((string) ($settings['amount'] ?? 0)));

        return ($settings['mode'] ?? 'cart') === 'unit'
            ? __('Adet başına :amount indirim', ['amount' => $amount])
            : __('Sepete :amount indirim', ['amount' => $amount]);
    }

    public function badge(array $settings): ?string
    {
        return __(':amount İndirim', ['amount' => Campaign::moneyCurrency($settings['currency'] ?? null)->format(Money::decimal($settings['amount'] ?? 0))]);
    }

    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome
    {
        $amount = app(CurrencyManager::class)->convert(
            Money::decimal($settings['amount'] ?? 0),
            Campaign::moneyCurrency($settings['currency'] ?? null),
            $context->currency,
        );
        $discounts = [];

        if (($settings['mode'] ?? 'cart') === 'unit') {
            foreach ($lines as $line) {
                $discounts[$line['product']->id] = Money::times($amount, $line['quantity']);
            }

            return new CampaignOutcome(discounts: $discounts);
        }

        $weights = [];
        foreach ($lines as $line) {
            $weights[$line['product']->id] = Money::times($line['unit_net'], $line['quantity']);
        }

        $total = Money::add(...array_values($weights));

        if (! $total->isPositive()) {
            return new CampaignOutcome;
        }

        // Sepete tek tutar: satırlara tutarları oranında, kuruşu kuruşuna (toplam korunur).
        return new CampaignOutcome(discounts: Money::allocate(Money::min(Money::round($amount), $total), $weights));
    }
}
