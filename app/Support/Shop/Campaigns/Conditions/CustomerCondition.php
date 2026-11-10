<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Enums\UserType;
use App\Models\User;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;

/**
 * Belirli müşteriler (müşteri ya da bayi hesabıyla giriş yapanlar).
 */
final class CustomerCondition extends Condition
{
    public function key(): string
    {
        return 'customer';
    }

    public function label(): string
    {
        return __('Belirli müşteri');
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
        return User::query()->where('user_type', '!=', UserType::Admin->value)
            ->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])
            ->all();
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        return Operators::within($context->viewer?->id, $operator, $value);
    }
}
