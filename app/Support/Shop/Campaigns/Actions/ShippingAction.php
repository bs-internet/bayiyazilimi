<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Actions;

use App\Support\Shop\Campaigns\CampaignAction;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\CampaignOutcome;

/**
 * Yalnız kargo kampanyası: indirim yoktur, kampanyanın kargo ayarı (ücretsiz /
 * sabit ücret) uygulanır. "Hangi ürünlere" koşulu varsa sepette bu ürünlerden
 * en az biri bulunmalıdır. Diğer işlemlerde de kargo ayrıca belirlenebilir.
 */
final class ShippingAction implements CampaignAction
{
    public const KEY = 'shipping';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Kargo kampanyası');
    }

    public function fields(): array
    {
        return [];
    }

    public function rules(): array
    {
        return [];
    }

    public function summary(array $settings): string
    {
        return __('Kargo kampanyası');
    }

    public function badge(array $settings): ?string
    {
        return null;
    }

    public function apply(CampaignContext $context, array $lines, array $settings): CampaignOutcome
    {
        return new CampaignOutcome;
    }
}
