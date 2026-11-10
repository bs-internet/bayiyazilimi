<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Shop\Campaigns\CampaignCondition;

/**
 * Koşul türleri için ortak varsayılanlar.
 */
abstract class Condition implements CampaignCondition
{
    public function options(): array
    {
        return [];
    }

    public function unit(): ?string
    {
        return null;
    }
}
