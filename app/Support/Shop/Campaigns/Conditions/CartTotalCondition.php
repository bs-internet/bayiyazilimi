<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns\Conditions;

use App\Services\Shop\CurrencyManager;
use App\Support\Shop\Campaigns\CampaignContext;
use App\Support\Shop\Campaigns\Operators;

/**
 * Sepet tutarı (KDV dahil, kampanya öncesi). Değer varsayılan para biriminde
 * girilir, sepet para birimine çevrilerek karşılaştırılır.
 */
final class CartTotalCondition extends Condition
{
    // CurrencyManager istek başınadır (scoped); kayıt kalıcı olduğundan her seferinde alınır.
    private function currencies(): CurrencyManager
    {
        return app(CurrencyManager::class);
    }

    public function key(): string
    {
        return 'cart_total';
    }

    public function label(): string
    {
        return __('Sepet tutarı (KDV dahil)');
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

    public function unit(): ?string
    {
        return $this->currencies()->base()->symbol ?: $this->currencies()->base()->code;
    }

    public function matches(CampaignContext $context, ?array $line, string $operator, mixed $value): bool
    {
        if ($context->preview) {
            return true;
        }

        $target = $this->currencies()->convert(is_numeric($value) ? (string) $value : '0', $this->currencies()->base(), $context->currency);

        return Operators::compare($context->cartGross(), $operator, $target);
    }
}
