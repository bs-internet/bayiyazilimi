<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Models\Campaign;
use App\Support\Shop\Campaigns\Actions\ShippingAction;

/**
 * Kampanya koşul ve işlem türlerinin kaydı (Hook + Registry). Çekirdek
 * türleri ShopServiceProvider ekler; modüller şu hook'larla yenisini ekler:
 *
 *     hooks()->action('shop.campaigns.conditions.register', fn (CampaignRegistry $r) => $r->addCondition(new MyCondition));
 *     hooks()->action('shop.campaigns.actions.register', fn (CampaignRegistry $r) => $r->addAction(new MyAction));
 *
 * Hook'lar ilk ihtiyaçta bir kez tetiklenir.
 */
final class CampaignRegistry
{
    /** @var array<string, CampaignCondition> */
    private array $conditions = [];

    /** @var array<string, CampaignAction> */
    private array $actions = [];

    private bool $collected = false;

    public function addCondition(CampaignCondition $condition): self
    {
        $this->conditions[$condition->key()] = $condition;

        return $this;
    }

    public function addAction(CampaignAction $action): self
    {
        $this->actions[$action->key()] = $action;

        return $this;
    }

    /**
     * @return array<string, CampaignCondition>
     */
    public function conditions(?string $scope = null): array
    {
        $this->collect();

        return $scope === null
            ? $this->conditions
            : array_filter($this->conditions, fn (CampaignCondition $c) => $c->scope() === $scope);
    }

    public function condition(string $key): ?CampaignCondition
    {
        $this->collect();

        return $this->conditions[$key] ?? null;
    }

    /**
     * @return array<string, CampaignAction>
     */
    public function actions(): array
    {
        $this->collect();

        return $this->actions;
    }

    public function action(string $key): ?CampaignAction
    {
        $this->collect();

        return $this->actions[$key] ?? null;
    }

    /**
     * Listede / sepette görünen özet: işlem + kargo (ör. "2 Al 1 Öde + ücretsiz kargo").
     * Kargo kampanyasında yalnız kargo.
     */
    public function summary(Campaign $campaign): string
    {
        $shipping = $this->shippingText($campaign);
        $action = $this->action($campaign->action);

        if ($action === null || $action->key() === ShippingAction::KEY) {
            return $shipping ?? ($action?->label() ?? $campaign->action);
        }

        $summary = $action->summary($campaign->settings());

        return $shipping !== null ? $summary.' + '.mb_strtolower(mb_substr($shipping, 0, 1)).mb_substr($shipping, 1) : $summary;
    }

    /**
     * Ürün kartı etiketi: girilen etiket, yoksa işlemden (kargo kampanyasında kargodan).
     */
    public function badge(Campaign $campaign): ?string
    {
        if ($campaign->badge) {
            return $campaign->badge;
        }

        if ($campaign->action === ShippingAction::KEY) {
            return match ($campaign->shipping_mode) {
                Campaign::SHIPPING_FREE => __('Kargo Bedava'),
                Campaign::SHIPPING_FIXED => $this->shippingText($campaign),
                default => null,
            };
        }

        return $this->action($campaign->action)?->badge($campaign->settings());
    }

    public function shippingText(Campaign $campaign): ?string
    {
        return match ($campaign->shipping_mode) {
            Campaign::SHIPPING_FREE => __('Ücretsiz kargo'),
            // Kargo ücreti her zaman varsayılan para biriminde (kargo tabloları gibi).
            Campaign::SHIPPING_FIXED => __('Kargo :amount', ['amount' => Campaign::moneyCurrency(null)->format($campaign->shipping_amount)]),
            default => null,
        };
    }

    private function collect(): void
    {
        if ($this->collected) {
            return;
        }

        $this->collected = true;
        hooks()->do('shop.campaigns.conditions.register', $this);
        hooks()->do('shop.campaigns.actions.register', $this);
    }
}
