<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Actions;

use App\Support\Money;
use App\Support\Shop\Campaigns\CampaignAction;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\CampaignOutcome;

/**
 * "X Al Y Öde": her satır (tek ürün) ayrı değerlendirilir; farklı ürünler
 * adet için birleştirilmez. Ücretsiz adet = floor(adet / X) × (X − Y).
 */
final class BuyXPayYAction implements CampaignAction
{
    public function key(): string
    {
        return 'buy_x_pay_y';
    }

    public function label(): string
    {
        return __('X Al Y Öde');
    }

    public function fields(): array
    {
        return [
            ['key' => 'buy', 'label' => __('Al'), 'type' => 'number', 'suffix' => __('adet')],
            ['key' => 'pay', 'label' => __('Öde'), 'type' => 'number', 'suffix' => __('adet')],
        ];
    }

    public function rules(): array
    {
        return [
            'settings.buy' => ['required', 'integer', 'min:2', 'max:1000'],
            'settings.pay' => ['required', 'integer', 'min:1', 'lt:settings.buy'],
        ];
    }

    public function summary(array $settings): string
    {
        return __(':buy Al :pay Öde', ['buy' => (int) ($settings['buy'] ?? 0), 'pay' => (int) ($settings['pay'] ?? 0)]);
    }

    public function badge(array $settings): ?string
    {
        return $this->summary($settings);
    }

    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome
    {
        $buy = (int) ($settings['buy'] ?? 0);
        $pay = (int) ($settings['pay'] ?? 0);

        if ($buy < 2 || $pay < 1 || $pay >= $buy) {
            return new CampaignOutcome;
        }

        $discounts = [];

        foreach ($lines as $line) {
            $free = intdiv(max(0, $line['quantity']), $buy) * ($buy - $pay);

            if ($free > 0) {
                $discounts[$line['product']->id] = Money::times($line['unit_net'], $free);
            }
        }

        return new CampaignOutcome(discounts: $discounts);
    }
}
