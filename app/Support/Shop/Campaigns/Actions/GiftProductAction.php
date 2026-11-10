<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Actions;

use App\Models\Product;
use App\Support\Shop\Campaigns\CampaignAction;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\CampaignOutcome;

/**
 * Hediye ürün: koşullar sağlanınca seçilen ürün sepete ücretsiz eklenir
 * (siparişe 0 tutarlı satır olarak geçer, stoktan düşer).
 */
final class GiftProductAction implements CampaignAction
{
    public function key(): string
    {
        return 'gift';
    }

    public function label(): string
    {
        return __('Hediye ürün');
    }

    public function fields(): array
    {
        return [
            ['key' => 'product_id', 'label' => __('Hediye ürün'), 'type' => 'product'],
            ['key' => 'quantity', 'label' => __('Adet'), 'type' => 'number', 'suffix' => __('adet')],
        ];
    }

    public function rules(): array
    {
        return [
            'settings.product_id' => ['required', 'uuid', 'exists:products,id'],
            'settings.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function summary(array $settings): string
    {
        $name = Product::query()->whereKey($settings['product_id'] ?? null)->value('name') ?? __('ürün');

        return __('Hediye: :name × :quantity', ['name' => $name, 'quantity' => (int) ($settings['quantity'] ?? 1)]);
    }

    public function badge(array $settings): ?string
    {
        return __('Hediyeli');
    }

    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome
    {
        if ($lines === [] || empty($settings['product_id'])) {
            return new CampaignOutcome;
        }

        return new CampaignOutcome(gifts: [[
            'product_id' => (string) $settings['product_id'],
            'quantity' => max(1, (int) ($settings['quantity'] ?? 1)),
        ]]);
    }
}
