<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Models\Campaign;
use App\Support\Shop\Campaigns\Actions\ShippingAction;
use App\Support\Shop\Campaigns\CampaignCondition;
use App\Support\Shop\Campaigns\CampaignRegistry;
use App\Support\Shop\Storefront;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Kampanya formu: genel bilgiler, "Kime / ne zaman" koşul grupları,
 * "Hangi ürünlere" koşulları ve işlem ayarları. Koşul ve işlem türleri
 * CampaignRegistry'den doğrulanır.
 */
class SaveCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $decimal = fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v;

        $this->merge([
            'badge' => trim((string) $this->input('badge', '')) ?: null,
            'starts_at' => $this->input('starts_at') ?: null,
            'ends_at' => $this->input('ends_at') ?: null,
            'audience_rules' => collect((array) $this->input('audience_rules', []))
                ->map(fn ($group) => collect((array) $group)->map(fn ($r) => $this->cleanRule($r, $decimal))->values()->all())
                ->filter(fn (array $group) => $group !== [])
                ->values()->all(),
            'product_rules' => collect((array) $this->input('product_rules', []))
                ->map(fn ($r) => $this->cleanRule($r, $decimal))->values()->all(),
            // Liste ayarları (ör. kademeler) formdan JSON metni olarak gelir.
            'settings' => collect((array) $this->input('settings', []))->map(function ($value) use ($decimal) {
                $list = is_string($value) && str_starts_with(trim($value), '[') ? json_decode($value, true) : $value;

                return is_array($list)
                    ? array_map(fn ($row) => is_array($row) ? array_map($decimal, $row) : $decimal($row), $list)
                    : $decimal($value);
            })->all(),
            'shipping_mode' => $this->input('shipping_mode') ?: Campaign::SHIPPING_CART,
            'shipping_amount' => $decimal($this->input('shipping_amount')) ?: null,
            'usage_limit' => $this->input('usage_limit') ?: null,
            'usage_limit_per_customer' => $this->input('usage_limit_per_customer') ?: null,
            'budget' => $decimal($this->input('budget')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $registry = app(CampaignRegistry::class);
        $action = $registry->action((string) $this->input('action'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:40'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'audience_rules' => ['array', 'max:10'],
            'audience_rules.*' => ['array', 'min:1', 'max:10'],
            'audience_rules.*.*.field' => ['required', Rule::in(array_keys($registry->conditions(CampaignCondition::AUDIENCE)))],
            'audience_rules.*.*.operator' => ['required', 'string'],
            'product_scope' => ['required', Rule::in([Campaign::SCOPE_ALL, Campaign::SCOPE_MATCH])],
            'product_rules' => ['array', 'max:10'],
            'product_rules.*.field' => ['required', Rule::in(array_keys($registry->conditions(CampaignCondition::PRODUCT)))],
            'product_rules.*.operator' => ['required', 'string'],
            'action' => ['required', Rule::in(array_keys($registry->actions()))],
            'settings' => ['array'],
            // Kargo kampanyasında kargo ücretsiz ya da sabit olmalı.
            'shipping_mode' => ['required', Rule::in($this->input('action') === ShippingAction::KEY
                ? [Campaign::SHIPPING_FREE, Campaign::SHIPPING_FIXED]
                : [Campaign::SHIPPING_CART, Campaign::SHIPPING_FREE, Campaign::SHIPPING_FIXED])],
            'shipping_amount' => ['nullable', 'required_if:shipping_mode,'.Campaign::SHIPPING_FIXED, 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', Rule::exists('currencies', 'code')],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'budget' => ['nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'combinable' => ['array', 'max:50'],
            'combinable.*' => ['uuid', 'distinct', Rule::exists('campaigns', 'id')],
            ...($action?->rules() ?? []),
        ];
    }

    /**
     * Mantık ve değer seçilen koşul türüne uymalı.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $registry = app(CampaignRegistry::class);

            $check = function (array $rule, string $key) use ($registry, $validator): void {
                $condition = $registry->condition((string) ($rule['field'] ?? ''));

                if ($condition === null) {
                    return;
                }

                if (! array_key_exists((string) ($rule['operator'] ?? ''), $condition->operators())) {
                    $validator->errors()->add("{$key}.operator", __('Geçersiz mantık.'));
                }

                $value = $rule['value'] ?? null;
                $empty = match ($condition->input()) {
                    'number' => ! is_numeric($value) || BigDecimal::of((string) $value)->isNegative(),
                    'multi', 'products' => ! is_array($value) || $value === [],
                    default => $value === null || $value === '',
                };

                if ($empty) {
                    $validator->errors()->add("{$key}.value", __(':condition için değer seçin.', ['condition' => $condition->label()]));
                }
            };

            foreach ((array) $this->input('audience_rules', []) as $g => $group) {
                foreach ((array) $group as $r => $rule) {
                    $check((array) $rule, "audience_rules.{$g}.{$r}");
                }
            }

            if ($this->input('product_scope') === Campaign::SCOPE_MATCH) {
                if ((array) $this->input('product_rules', []) === []) {
                    $validator->errors()->add('product_rules', __('Koşula uyan ürünler için en az bir koşul ekleyin.'));
                }

                foreach ((array) $this->input('product_rules', []) as $r => $rule) {
                    $check((array) $rule, "product_rules.{$r}");
                }
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        $match = $data['product_scope'] === Campaign::SCOPE_MATCH;

        return [
            'name' => $data['name'],
            // Vitrin yokken etiket formda yoktur; kayıtlı değere dokunulmaz.
            ...(Storefront::enabled() ? ['badge' => $data['badge'] ?? null] : []),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'audience_rules' => $this->input('audience_rules', []),
            'product_scope' => $data['product_scope'],
            'product_rules' => $match ? $this->input('product_rules', []) : [],
            'action' => $data['action'],
            'shipping_mode' => $data['shipping_mode'],
            'shipping_amount' => $data['shipping_mode'] === Campaign::SHIPPING_FIXED ? $data['shipping_amount'] : null,
            'currency_code' => $data['currency_code'] ?? null,
            'combinable' => array_values($data['combinable'] ?? []),
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'usage_limit_per_customer' => isset($data['usage_limit_per_customer']) ? (int) $data['usage_limit_per_customer'] : null,
            'budget' => $data['budget'] ?? null,
            // Yalnız seçilen işlemin alanları saklanır.
            'action_settings' => collect($this->input('settings', []))
                ->only(collect(app(CampaignRegistry::class)->action($data['action'])?->fields() ?? [])->pluck('key'))
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('kampanya adı'),
            'badge' => __('vitrin etiketi'),
            'starts_at' => __('başlangıç'),
            'ends_at' => __('bitiş'),
            'product_rules' => __('ürün koşulları'),
            'action' => __('işlem'),
            'settings.rate' => __('indirim oranı'),
            'settings.amount' => __('indirim tutarı'),
            'settings.mode' => __('uygulama'),
            'settings.buy' => __('alınan adet'),
            'settings.pay' => __('ödenen adet'),
            'settings.product_id' => __('hediye ürün'),
            'settings.quantity' => __('adet'),
            'shipping_mode' => __('kargo'),
            'shipping_amount' => __('kargo ücreti'),
            'currency_code' => __('para birimi'),
            'combinable' => __('birlikte kullanılacak kampanyalar'),
            'usage_limit' => __('toplam kullanım sınırı'),
            'usage_limit_per_customer' => __('müşteri başına kullanım sınırı'),
            'budget' => __('kampanya bütçesi'),
            'combinable.*' => __('birlikte kullanılacak kampanya'),
        ];
    }

    /**
     * @param  mixed  $rule
     * @return array{field: string, operator: string, value: mixed}
     */
    private function cleanRule($rule, callable $decimal): array
    {
        $rule = (array) $rule;
        $value = $rule['value'] ?? null;

        return [
            'field' => (string) ($rule['field'] ?? ''),
            'operator' => (string) ($rule['operator'] ?? ''),
            'value' => match (true) {
                is_array($value) => array_values(array_filter(array_map('strval', $value))),
                // Sayı değilse (ör. kupon kodu) olduğu gibi; sayıda ondalık virgül noktaya çevrilir.
                is_string($value) && ! is_numeric(str_replace(',', '.', trim($value))) => trim($value),
                default => $decimal($value),
            },
        ];
    }
}
