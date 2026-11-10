<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;
use Carbon\CarbonImmutable;

/**
 * Günün saati (0–23; ör. "saat en az 18" ve "saat en fazla 21" → 18:00–21:59).
 * Uygulamanın saat dilimine göre, istek anında.
 */
final class HourCondition extends Condition
{
    public function key(): string
    {
        return 'hour';
    }

    public function label(): string
    {
        return __('Saat');
    }

    public function scope(): string
    {
        return self::AUDIENCE;
    }

    public function operators(): array
    {
        return Operators::numeric();
    }

    public function input(): string
    {
        return 'number';
    }

    public function unit(): string
    {
        return __('saat');
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::compare(CarbonImmutable::now()->hour, $operator, $value);
    }
}
