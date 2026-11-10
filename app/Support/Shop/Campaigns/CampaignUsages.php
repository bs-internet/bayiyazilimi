<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Enums\Shop\OrderStatus;
use App\Exceptions\Shop\ShopException;
use App\Models\Campaign;
use App\Models\CampaignUsage;
use App\Models\Order;
use App\Services\Shop\CurrencyManager;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kampanya kullanım limitleri: toplam kullanım, müşteri başına kullanım ve
 * bütçe (toplam kazanç tavanı, ana para biriminde). İptal / başarısız
 * siparişlerdeki kullanımlar sayılmaz.
 *
 * Müşteri: hesap (bayi), yoksa üye, yoksa e-posta (misafir; e-posta ödeme
 * adımında bilinir). Sipariş verilirken kampanya satırı kilitlenip limit yeniden
 * denetlenir; eşzamanlı iki sipariş son kullanımı paylaşamaz.
 */
final class CampaignUsages
{
    /**
     * Sipariş anındaki yeniden denetimde bağlama eklenen öznitelik: o an yazılan
     * siparişin id'si. Sipariş geçmişine bakan koşullar (ör. ilk sipariş) bu siparişi
     * saymaz ve kilitli okur (eşzamanlı siparişi görmek için).
     */
    public const CLAIM_ORDER = 'claim_order_id';

    /** @var array<string, array{count: int, value: BigDecimal, mine: int}> istek içi önbellek */
    private array $stats = [];

    public function __construct(private readonly CurrencyManager $currencies) {}

    public function flush(): void
    {
        $this->stats = [];
    }

    /**
     * Kampanya bu sepete (bu kazançla, sepet para biriminde) uygulanabilir mi.
     */
    public function allows(Campaign $campaign, CampaignContext $context, BigDecimal $value): bool
    {
        if (! $campaign->hasLimits()) {
            return true;
        }

        $stats = $this->stats[$this->cacheKey($campaign, $context)] ??= $this->load($campaign, $context);

        if ($campaign->usage_limit !== null && $stats['count'] >= $campaign->usage_limit) {
            return false;
        }

        if ($campaign->usage_limit_per_customer !== null && $stats['mine'] >= $campaign->usage_limit_per_customer) {
            return false;
        }

        if ($campaign->budget !== null) {
            $base = $this->toBase($value, $context);

            if (Money::add($stats['value'], $base)->isGreaterThan($campaign->budget)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sipariş transaction'ı içinde: kampanyaları kilitleyip limitleri yeniden
     * denetler ve kullanımı yazar. Limit dolduysa ShopException (sepet bir
     * sonraki hesaplamada kampanyayı uygulamaz).
     */
    public function claim(CampaignResult $result, CampaignContext $context, Order $order): void
    {
        $this->flush();

        foreach ($result->campaigns ?: [$result->campaign] as $campaign) {
            $locked = Campaign::query()->lockForUpdate()->find($campaign->id);

            // Fiyatlama ile sipariş arasında pasifleşen / süresi biten kampanya uygulanmaz.
            if ($locked === null || ! $locked->isLiveAt(CarbonImmutable::now())) {
                throw new ShopException(__('":name" kampanyası artık geçerli değil; sepetinizi kontrol edin.', ['name' => $campaign->name]));
            }

            // Hedef kitle (ör. "ilk sipariş") kilit altında yeniden denetlenir: aynı müşterinin
            // eşzamanlı iki siparişi birlikte "ilk sipariş" indirimi alamaz. Koşullar bu
            // siparişi saymaz ve kilitli okur (`claim_order_id`).
            $claimContext = new CampaignContext(
                $context->lines, $context->currency, $context->viewer, $context->viewerIsDealer, false,
                $context->email, $context->paymentMethod, $context->city,
                [...$context->attributes, self::CLAIM_ORDER => $order->id],
            );

            if (! app(CampaignEngine::class)->audienceMatches($locked, $claimContext)) {
                $this->flush();

                throw new ShopException(__('":name" kampanyası bu sipariş için artık geçerli değil; sepetiniz güncellendi, lütfen tutarları kontrol edin.', ['name' => $campaign->name]));
            }

            $value = $result->values[$campaign->id] ?? Money::round(0);

            if (! $this->allows($locked, $context, $value)) {
                $this->flush();

                throw new ShopException(__('":name" kampanyasının kullanım sınırı doldu; sepetiniz güncellendi, lütfen tutarları kontrol edin.', ['name' => $campaign->name]));
            }

            CampaignUsage::query()->create([
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'order_id' => $order->id,
                'account_id' => $order->account_id,
                'user_id' => $order->user_id,
                'email' => $this->email($context->email ?? $order->email),
                'value' => $this->toBase($value, $context),
            ]);
        }

        $this->flush();
    }

    /**
     * Panel listesi: kampanya id => [kullanım, kazanç].
     *
     * @param  array<int, string>  $campaignIds
     * @return array<string, array{count: int, value: BigDecimal}>
     */
    public function totals(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        return $this->valid(CampaignUsage::query())
            ->whereIn('campaign_usages.campaign_id', $campaignIds)
            ->groupBy('campaign_usages.campaign_id')
            ->selectRaw('campaign_usages.campaign_id as id, count(*) as uses, coalesce(sum(campaign_usages.value), 0) as total')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $r) => [(string) $r->id => ['count' => (int) $r->uses, 'value' => Money::round($r->total)]])
            ->all();
    }

    /**
     * @return array{count: int, value: BigDecimal, mine: int}
     */
    private function load(Campaign $campaign, CampaignContext $context): array
    {
        $row = $this->valid(CampaignUsage::query())
            ->where('campaign_usages.campaign_id', $campaign->id)
            ->selectRaw('count(*) as uses, coalesce(sum(campaign_usages.value), 0) as total')
            ->toBase()
            ->first();

        $mine = 0;

        if ($campaign->usage_limit_per_customer !== null) {
            $identity = $this->identity($context);

            $mine = $identity === [] ? 0 : $this->valid(CampaignUsage::query())
                ->where('campaign_usages.campaign_id', $campaign->id)
                ->where(function (Builder $q) use ($identity): void {
                    foreach ($identity as $column => $value) {
                        $q->orWhere("campaign_usages.{$column}", $value);
                    }
                })
                ->count();
        }

        return ['count' => (int) ($row->uses ?? 0), 'value' => Money::round($row->total ?? 0), 'mine' => $mine];
    }

    /**
     * @param  Builder<CampaignUsage>  $query
     * @return Builder<CampaignUsage>
     */
    private function valid(Builder $query): Builder
    {
        return $query->join('orders', 'orders.id', '=', 'campaign_usages.order_id')
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Failed->value]);
    }

    /**
     * Müşteriyi tanıtan alanlar (biri eşleşirse aynı müşteri).
     *
     * @return array<string, string>
     */
    private function identity(CampaignContext $context): array
    {
        return array_filter([
            'account_id' => $context->viewer?->account_id,
            'user_id' => $context->viewer?->id,
            'email' => $this->email($context->email),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function cacheKey(Campaign $campaign, CampaignContext $context): string
    {
        return $campaign->id.'|'.implode('|', $this->identity($context));
    }

    private function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function toBase(BigDecimal $value, CampaignContext $context): BigDecimal
    {
        return Money::round($this->currencies->convert($value, $context->currency, $this->currencies->base()));
    }
}
