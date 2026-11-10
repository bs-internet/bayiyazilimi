<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Models\DealerGroup;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;

/**
 * Bayi grubu (yalnız bayilerde dolu; misafir / müşteri hiçbir grupta değildir).
 */
final class DealerGroupCondition extends Condition
{
    public function key(): string
    {
        return 'dealer_group';
    }

    public function label(): string
    {
        return __('Bayi grubu');
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
        return DealerGroup::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (DealerGroup $g) => ['value' => $g->id, 'label' => $g->name])
            ->all();
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        $group = $context->viewerIsDealer ? $context->viewer?->loadMissing('account')->account?->dealer_group_id : null;

        return Operators::within($group, $operator, $value);
    }
}
