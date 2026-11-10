<?php

declare(strict_types=1);

namespace App\Support\Shop\Campaigns;

use App\Models\Campaign;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\PriceCalculator;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Kampanyaları sepete uygular. Sepette normalde tek kampanya geçerlidir:
 * koşulları sağlayanlar arasından müşteriye en çok kazandıran (indirim + hediye
 * değeri + kargo kazancı) seçilir; eşitlikte sıra (sort_order) öndeki kazanır.
 * Birbiriyle birleşebilir işaretlenmiş kampanyalar aynı sepette birlikte
 * uygulanır: en çok kazandıran birleşim seçilir; aynı satırdaki indirimlerin
 * toplamı satır tutarını geçemez, kargoda en düşük ücret uygulanır.
 * Kampanyanın kargosu ayrıca belirlenir: sepete göre (normal), ücretsiz ya da
 * sabit ücret. Kampanyalar yalnız sepete uygulanır, teklif listesine uygulanmaz.
 */
final class CampaignEngine
{
    /** @var array<int, Campaign>|null aktif kampanyalar (istek başına; motor scoped) */
    private ?array $campaigns = null;

    public function __construct(
        private readonly CampaignRegistry $registry,
        private readonly CurrencyManager $currencies,
        private readonly PriceCalculator $prices,
        private readonly CampaignUsages $usages,
    ) {}

    public function flush(): void
    {
        $this->campaigns = null;
    }

    /**
     * @param  Closure(): BigDecimal  $shippingEstimate  kampanyasız kargo ücreti (KDV dahil, sepet para biriminde)
     */
    public function evaluate(CampaignContext $context, Closure $shippingEstimate): ?CampaignResult
    {
        if ($context->lines === []) {
            return null;
        }

        $limits = [];
        foreach ($context->lines as $line) {
            $limits[$line['product']->id] = Money::times($line['unit_net'], $line['quantity']);
        }

        $estimate = null;
        $estimateFn = function () use (&$estimate, $shippingEstimate): BigDecimal {
            return $estimate ??= Money::max(0, $shippingEstimate());
        };

        // Sepete uyan her kampanyanın tek başına etkisi.
        $candidates = [];

        foreach ($this->live() as $campaign) {
            $candidate = $this->candidate($campaign, $context, $limits, $estimateFn);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        $best = $this->bestCombination($candidates, $limits, $estimateFn);
        $chosen = $best['set'];
        $primary = $chosen[0]['campaign'];

        // Satır indirimi: birleşimdeki kampanyaların toplamı, satır tutarıyla sınırlı.
        $discounts = [];
        foreach ($best['discounts'] as $productId => $amount) {
            $labels = array_map(
                fn (array $c) => $c['campaign']->name,
                array_filter($chosen, fn (array $c) => isset($c['discounts'][$productId])),
            );
            $discounts[$productId] = new CampaignDiscount($productId, $amount, implode(' + ', $labels), $primary->id);
        }

        return new CampaignResult(
            campaign: $primary,
            summary: implode(' + ', array_map(fn (array $c) => $this->registry->summary($c['campaign']), $chosen)),
            discounts: $discounts,
            shipping: $best['shipping'],
            gifts: $best['gifts'],
            value: $best['value'],
            others: count($candidates) - count($chosen),
            campaigns: array_map(fn (array $c) => $c['campaign'], $chosen),
            shippingCampaign: $best['shippingCampaign'],
            values: array_column(array_map(fn (array $c) => [$c['campaign']->id, $c['value']], $chosen), 1, 0),
        );
    }

    /**
     * Kampanyanın bu sepetteki tek başına etkisi; uymuyor ya da kazanç yoksa null.
     *
     * @param  array<string, BigDecimal>  $limits
     * @param  Closure(): BigDecimal  $estimate
     * @return array{campaign: Campaign, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, giftValue: BigDecimal, shipping: ?BigDecimal, value: BigDecimal}|null
     */
    private function candidate(Campaign $campaign, CampaignContext $context, array $limits, Closure $estimate): ?array
    {
        $action = $this->registry->action($campaign->action);

        if ($action === null || ! $this->audienceMatches($campaign, $context)) {
            return null;
        }

        $lines = array_values(array_filter($context->lines, fn (array $l) => $this->productMatches($campaign, $context, $l)));

        if ($lines === []) {
            return null;
        }

        $outcome = $action->apply($context, $lines, $campaign->settings());

        $discounts = [];
        foreach ($outcome->discounts as $productId => $amount) {
            $amount = Money::min(Money::max($amount, 0), $limits[$productId] ?? 0);

            if ($amount->isPositive()) {
                $discounts[$productId] = $amount;
            }
        }

        $gifts = $this->gifts($outcome->gifts, $context);
        $giftValue = Money::add(...array_map(fn (array $g) => Money::times($this->prices->net($g['product'], $context->currency), $g['quantity']), $gifts));
        $shipping = $this->shippingFor($campaign, $context);

        // Kargo: ücretsiz → kargonun tamamı, sabit → normal ücretle farkı kazançtır.
        $value = Money::add(...array_values($discounts), ...[$giftValue]);
        if ($shipping !== null) {
            $value = Money::add($value, Money::max(0, Money::sub($estimate(), $shipping)));
        }

        // Kullanım limiti / bütçe dolmuşsa uygulanmaz.
        if (! $value->isPositive() || ! $this->usages->allows($campaign, $context, $value)) {
            return null;
        }

        return compact('campaign', 'discounts', 'gifts', 'giftValue', 'shipping', 'value');
    }

    /**
     * En çok kazandıran birleşim: her ikilisi birbiriyle birleşebilen kampanya
     * kümeleri arasından. Eşitlikte sırada önde olan (tek başına kampanya dahil) kalır.
     *
     * @param  array<int, array{campaign: Campaign, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, giftValue: BigDecimal, shipping: ?BigDecimal, value: BigDecimal}>  $candidates
     * @param  array<string, BigDecimal>  $limits
     * @param  Closure(): BigDecimal  $estimate
     * @return array{set: non-empty-list<array{campaign: Campaign, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, giftValue: BigDecimal, shipping: ?BigDecimal, value: BigDecimal}>, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, shipping: ?BigDecimal, shippingCampaign: ?Campaign, value: BigDecimal}
     */
    private function bestCombination(array $candidates, array $limits, Closure $estimate): array
    {
        $count = count($candidates);
        $best = null;

        // Kümeler sırayla (öndeki kampanyalar önce) gezilir; kampanya sayısı küçüktür.
        $visit = function (array $set, int $from) use (&$visit, &$best, $candidates, $count, $limits, $estimate): void {
            if ($set !== []) {
                $combined = $this->combine($set, $limits, $estimate);

                if ($best === null || $combined['value']->isGreaterThan($best['value'])) {
                    $best = $combined;
                }
            }

            // Çok sayıda kampanyada aramayı sınırla (12'den sonrası tek tek eklenmez).
            if (count($set) >= 6) {
                return;
            }

            for ($i = $from; $i < min($count, 12); $i++) {
                $next = $candidates[$i];

                foreach ($set as $member) {
                    if (! $member['campaign']->combinesWith($next['campaign']) && ! $next['campaign']->combinesWith($member['campaign'])) {
                        continue 2;
                    }
                }

                $visit([...$set, $next], $i + 1);
            }
        };

        $visit([], 0);

        // 12'den sonraki kampanyalar yalnız tek başına değerlendirilir.
        for ($i = 12; $i < $count; $i++) {
            $single = $this->combine([$candidates[$i]], $limits, $estimate);

            if ($best === null || $single['value']->isGreaterThan($best['value'])) {
                $best = $single;
            }
        }

        /** @var array{set: non-empty-list<array{campaign: Campaign, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, giftValue: BigDecimal, shipping: ?BigDecimal, value: BigDecimal}>, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, shipping: ?BigDecimal, shippingCampaign: ?Campaign, value: BigDecimal} $best */
        return $best;
    }

    /**
     * Kampanya kümesinin birlikte etkisi.
     *
     * @param  non-empty-list<array{campaign: Campaign, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, giftValue: BigDecimal, shipping: ?BigDecimal, value: BigDecimal}>  $set
     * @param  array<string, BigDecimal>  $limits
     * @param  Closure(): BigDecimal  $estimate
     * @return array{set: non-empty-list<array{campaign: Campaign, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, giftValue: BigDecimal, shipping: ?BigDecimal, value: BigDecimal}>, discounts: array<string, BigDecimal>, gifts: array<int, array{product: Product, quantity: int}>, shipping: ?BigDecimal, shippingCampaign: ?Campaign, value: BigDecimal}
     */
    private function combine(array $set, array $limits, Closure $estimate): array
    {
        $discounts = [];
        $gifts = [];
        $giftValue = Money::round(0);
        $shipping = null;
        $shippingCampaign = null;

        foreach ($set as $member) {
            foreach ($member['discounts'] as $productId => $amount) {
                $discounts[$productId] = Money::min(Money::add($discounts[$productId] ?? 0, $amount), $limits[$productId] ?? 0);
            }

            $gifts = [...$gifts, ...$member['gifts']];
            $giftValue = Money::add($giftValue, $member['giftValue']);

            if ($member['shipping'] !== null && ($shipping === null || $member['shipping']->isLessThan($shipping))) {
                $shipping = $member['shipping'];
                $shippingCampaign = $member['campaign'];
            }
        }

        $value = Money::add($giftValue, ...array_values($discounts));
        if ($shipping !== null) {
            $value = Money::add($value, Money::max(0, Money::sub($estimate(), $shipping)));
        }

        return compact('set', 'discounts', 'gifts', 'shipping', 'shippingCampaign', 'value');
    }

    /**
     * "Şu kadar daha ekleyin" teşviki: yalnız sepet tutarı eksik kaldığı için
     * uygulanmayan (ya da kademeli indirimde bir üst kademeye geçilecek)
     * kampanyalar arasından en az tutar gerekeni. Tutar KDV dahil, sepet para
     * biriminde; uygun kampanya yoksa null.
     *
     * @return array{amount: BigDecimal, name: string, summary: string}|null
     */
    public function nudge(CampaignContext $context, ?CampaignResult $applied): ?array
    {
        if ($context->lines === []) {
            return null;
        }

        $gross = $context->cartGross();
        $appliedIds = array_map(fn (Campaign $c) => $c->id, $applied?->campaigns ?: ($applied !== null ? [$applied->campaign] : []));
        $best = null;

        foreach ($this->live() as $campaign) {
            $action = $this->registry->action($campaign->action);

            if ($action === null || ! $this->usages->allows($campaign, $context, Money::round(0))) {
                continue;
            }

            // Hangi ürünlere koşulu varsa sepette uyan satır olmalı.
            if (array_filter($context->lines, fn (array $l) => $this->productMatches($campaign, $context, $l)) === []) {
                continue;
            }

            $target = in_array($campaign->id, $appliedIds, true)
                ? $this->nextTier($campaign, $context, $gross)
                : $this->missingCartTotal($campaign, $context);

            if ($target === null || ! $target->isGreaterThan($gross)) {
                continue;
            }

            $amount = Money::round(Money::sub($target, $gross));

            if ($best === null || $amount->isLessThan($best['amount'])) {
                $best = ['amount' => $amount, 'name' => $campaign->name, 'summary' => $this->registry->summary($campaign)];
            }
        }

        return $best;
    }

    /**
     * Kampanyanın tek eksiği sepet tutarıysa ulaşılması gereken tutar (sepet
     * para biriminde); başka koşul da sağlanmıyorsa null.
     */
    private function missingCartTotal(Campaign $campaign, CampaignContext $context): ?BigDecimal
    {
        $groups = array_values(array_filter($campaign->audience_rules ?? [], fn (array $g) => $g !== []));
        $best = null;

        foreach ($groups as $rules) {
            $target = null;

            foreach ($rules as $rule) {
                $condition = $this->registry->condition($rule['field']);

                if ($condition === null || $condition->scope() !== CampaignCondition::AUDIENCE) {
                    continue 2;
                }

                if ($condition->matches($context, null, $rule['operator'], $rule['value'])) {
                    continue;
                }

                // Sağlanmayan koşul yalnız "sepet tutarı en az" olabilir.
                if (! $condition instanceof Conditions\CartTotalCondition || $rule['operator'] !== Operators::GTE || ! is_numeric($rule['value'])) {
                    continue 2;
                }

                $value = Money::round($this->currencies->convert((string) $rule['value'], $this->currencies->base(), $context->currency));
                $target = $target === null ? $value : Money::max($target, $value);
            }

            if ($target !== null && ($best === null || $target->isLessThan($best))) {
                $best = $target;
            }
        }

        return $best;
    }

    /**
     * Uygulanan sepet tutarlı kademeli indirimde bir sonraki kademe (sepet para biriminde).
     */
    private function nextTier(Campaign $campaign, CampaignContext $context, BigDecimal $gross): ?BigDecimal
    {
        $settings = $campaign->settings();

        if ($campaign->action !== 'tiered' || ($settings['basis'] ?? null) !== Actions\TieredDiscountAction::CART) {
            return null;
        }

        $currency = Campaign::moneyCurrency($settings['currency'] ?? null);
        $next = null;

        foreach ((array) ($settings['tiers'] ?? []) as $tier) {
            if (! is_array($tier) || ! is_numeric($tier['min'] ?? null)) {
                continue;
            }

            $min = Money::round($this->currencies->convert((string) $tier['min'], $currency, $context->currency));

            if ($min->isGreaterThan($gross) && ($next === null || $min->isLessThan($next))) {
                $next = $min;
            }
        }

        return $next;
    }

    /**
     * Ürün kartı "sepette" fiyatı: ürün en az sipariş adediyle tek başına sepete
     * konsaydı kampanya sonrası birim fiyatı (KDV hariç, verilen para biriminde).
     * Sepetle aynı kural: tutar / adet koşulları gerçekten denetlenir; ödeme
     * yöntemi, il gibi ödeme adımı koşulları bilinmediği için sağlanmaz. Kampanya
     * indirimi yoksa null (kargo ve hediye kazancı fiyata yansımaz).
     */
    public function unitPriceFor(Product $product, Currency $currency, ?User $viewer): ?BigDecimal
    {
        // Fiyatı gösterilen ürün sepete eklenebilir (bkz. Product::showsPrice).
        if (! $product->showsPrice()) {
            return null;
        }

        $quantity = max(1, (int) $product->min_qty);
        $unitNet = $this->prices->net($product, $currency);

        $result = $this->evaluate(new CampaignContext(
            lines: [['product' => $product, 'quantity' => $quantity, 'unit_net' => $unitNet, 'tax_rate' => Money::decimal($product->tax_rate)]],
            currency: $currency,
            viewer: $viewer,
            viewerIsDealer: $viewer !== null && $viewer->account_id !== null && $viewer->loadMissing('account')->isDealer(),
        ), fn () => Money::round(0));

        $discount = $result?->discounts[$product->id] ?? null;

        if ($discount === null || ! $discount->amount->isPositive()) {
            return null;
        }

        return Money::div(Money::sub(Money::times($unitNet, $quantity, 4), $discount->amount), $quantity, 4);
    }

    /**
     * Ürün kartı / sayfası etiketleri: ürünü kapsayan ve izleyiciye açık
     * kampanyalar (sepet tutarı / adet koşulları sağlanmış sayılır).
     *
     * @return array<int, string>
     */
    public function badgesFor(Product $product, ?User $viewer): array
    {
        // Kampanyalar yalnızca sepete uygulanır; sepete eklenemeyen ürüne etiket yok.
        if (! $product->sale_mode->allowsCart() || ! $product->showsPrice()) {
            return [];
        }

        $context = new CampaignContext(
            lines: [['product' => $product, 'quantity' => 1, 'unit_net' => Money::round(0, 4), 'tax_rate' => Money::round(0)]],
            currency: $this->currencies->current(),
            viewer: $viewer,
            viewerIsDealer: $viewer !== null && $viewer->account_id !== null && $viewer->loadMissing('account')->isDealer(),
            preview: true,
        );

        $badges = [];

        foreach ($this->live() as $campaign) {
            if ($this->registry->action($campaign->action) === null
                || ! $this->audienceMatches($campaign, $context)
                || ! $this->productMatches($campaign, $context, $context->lines[0])) {
                continue;
            }

            $badge = $this->registry->badge($campaign);

            if ($badge !== null && $badge !== '') {
                $badges[] = $badge;
            }
        }

        return array_values(array_unique($badges));
    }

    /**
     * Vitrin "Kampanyalı Ürünler": ürün sorgusunu izleyiciye açık kampanyaların
     * kapsadığı ürünlere daraltır. Etiketlerle aynı kural: sepete bağlı koşullar
     * (tutar, adet) sağlanmış sayılır. Ürün sorguya çevrilemeyen bir koşul
     * içeren kampanya listeye katılmaz; hiç kampanya yoksa sonuç boştur.
     */
    public function constrainCovered(Builder $products, ?User $viewer): void
    {
        $context = new CampaignContext(
            lines: [],
            currency: $this->currencies->current(),
            viewer: $viewer,
            viewerIsDealer: $viewer !== null && $viewer->account_id !== null && $viewer->loadMissing('account')->isDealer(),
            preview: true,
        );

        $scopes = [];

        foreach ($this->live() as $campaign) {
            if ($this->registry->action($campaign->action) === null || ! $this->audienceMatches($campaign, $context)) {
                continue;
            }

            $rules = $campaign->product_scope === Campaign::SCOPE_MATCH ? (array) $campaign->product_rules : [];

            // Ürün koşulu yok: bütün ürünler kampanyalı.
            if ($rules === []) {
                return;
            }

            $constraints = [];
            foreach ($rules as $rule) {
                $condition = $this->registry->condition($rule['field']);

                if (! $condition instanceof ConstrainsProducts || $condition->scope() !== CampaignCondition::PRODUCT) {
                    continue 2;
                }

                $constraints[] = [$condition, $rule['operator'], $rule['value']];
            }

            $scopes[] = $constraints;
        }

        if ($scopes === []) {
            $products->whereRaw('1 = 0');

            return;
        }

        $products->where(function (Builder $any) use ($scopes): void {
            foreach ($scopes as $constraints) {
                $any->orWhere(function (Builder $all) use ($constraints): void {
                    foreach ($constraints as [$condition, $operator, $value]) {
                        $condition->constrain($all, $operator, $value);
                    }
                });
            }
        });
    }

    /**
     * Kampanyanın kargo ücreti (sepet para biriminde, KDV dahil); `null` = sepete göre (normal).
     */
    private function shippingFor(Campaign $campaign, CampaignContext $context): ?BigDecimal
    {
        return match ($campaign->shipping_mode) {
            Campaign::SHIPPING_FREE => Money::round(0),
            // Sabit kargo varsayılan para biriminde girilir.
            Campaign::SHIPPING_FIXED => Money::round($this->currencies->convert($campaign->shipping_amount ?? 0, $this->currencies->base(), $context->currency)),
            default => null,
        };
    }

    /**
     * Gruplardan biri tamamen sağlanırsa (grup içi VE, gruplar arası VEYA). Grup yoksa herkese.
     * Sipariş anında (CampaignUsages::claim) kilit altında yeniden çağrılır.
     */
    public function audienceMatches(Campaign $campaign, CampaignContext $context): bool
    {
        $groups = array_values(array_filter((array) $campaign->audience_rules, fn ($g) => is_array($g) && $g !== []));

        if ($groups === []) {
            return true;
        }

        foreach ($groups as $rules) {
            if ($this->allMatch($rules, $context, null, CampaignCondition::AUDIENCE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{product: Product, quantity: int, unit_net: BigDecimal, tax_rate: BigDecimal}  $line
     */
    private function productMatches(Campaign $campaign, CampaignContext $context, array $line): bool
    {
        if ($campaign->product_scope !== Campaign::SCOPE_MATCH) {
            return true;
        }

        return $this->allMatch((array) $campaign->product_rules, $context, $line, CampaignCondition::PRODUCT);
    }

    /**
     * Bilinmeyen (ör. kaldırılmış modülün) koşulu sağlanmamış sayılır.
     *
     * @param  array<int, mixed>  $rules
     * @param  array{product: Product, quantity: int, unit_net: BigDecimal, tax_rate: BigDecimal}|null  $line
     */
    private function allMatch(array $rules, CampaignContext $context, ?array $line, string $scope): bool
    {
        foreach ($rules as $rule) {
            $condition = is_array($rule) ? $this->registry->condition((string) ($rule['field'] ?? '')) : null;

            if ($condition === null || $condition->scope() !== $scope
                || ! $condition->matches($context, $line, (string) ($rule['operator'] ?? ''), $rule['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hediye edilebilir ürünler: görünür, sepete eklenebilir ve stokta (sepetteki adetle birlikte).
     *
     * @param  array<int, array{product_id: string, quantity: int}>  $gifts
     * @return array<int, array{product: Product, quantity: int}>
     */
    private function gifts(array $gifts, CampaignContext $context): array
    {
        if ($gifts === []) {
            return [];
        }

        $products = Product::query()->visible()->with(['currency', 'images', 'brand'])
            ->whereIn('id', array_column($gifts, 'product_id'))->get()->keyBy('id');
        $inCart = [];
        foreach ($context->lines as $line) {
            $inCart[$line['product']->id] = $line['quantity'];
        }
        $result = [];

        foreach ($gifts as $gift) {
            $product = $products->get($gift['product_id']);

            if ($product === null || ! $product->sale_mode->allowsCart()
                || ! $product->hasStockFor($gift['quantity'] + ($inCart[$product->id] ?? 0))) {
                continue;
            }

            $result[] = ['product' => $product, 'quantity' => $gift['quantity']];
        }

        return $result;
    }

    /**
     * @return array<int, Campaign>
     */
    private function live(): array
    {
        $now = CarbonImmutable::now();

        // Tarih aralığı her çağrıda yeniden denetlenir (aynı istekte saat ilerleyebilir).
        $this->campaigns ??= Campaign::query()->with('combinables:id')->where('status', 'active')->ordered()->oldest()->get()->all();

        return array_values(array_filter($this->campaigns, fn (Campaign $c) => $c->isLiveAt($now)));
    }
}
