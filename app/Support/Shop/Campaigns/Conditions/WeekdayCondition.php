<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;
use Carbon\CarbonImmutable;

/**
 * Haftanın günü (ör. hafta sonu). Uygulamanın saat dilimine göre, istek anında.
 */
final class WeekdayCondition extends Condition
{
    public function key(): string
    {
        return 'weekday';
    }

    public function label(): string
    {
        return __('Gün');
    }

    public function scope(): string
    {
        return self::AUDIENCE;
    }

    public function operators(): array
    {
        return Operators::membership();
    }

    public function input(): string
    {
        return 'multi';
    }

    public function options(): array
    {
        return [
            ['value' => '1', 'label' => __('Pazartesi')],
            ['value' => '2', 'label' => __('Salı')],
            ['value' => '3', 'label' => __('Çarşamba')],
            ['value' => '4', 'label' => __('Perşembe')],
            ['value' => '5', 'label' => __('Cuma')],
            ['value' => '6', 'label' => __('Cumartesi')],
            ['value' => '7', 'label' => __('Pazar')],
        ];
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::within((string) CarbonImmutable::now()->dayOfWeekIso, $operator, $value);
    }
}
