<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Models\Currency;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;

/**
 * Sepetin para birimi.
 */
final class CurrencyCondition extends Condition
{
    public function key(): string
    {
        return 'currency';
    }

    public function label(): string
    {
        return __('Para birimi');
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
        return Currency::query()->active()->ordered()->get(['id', 'code', 'name'])
            ->map(fn (Currency $c) => ['value' => $c->code, 'label' => "{$c->code} — {$c->name}"])
            ->all();
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::within($context->currency->code, $operator, $value);
    }
}
