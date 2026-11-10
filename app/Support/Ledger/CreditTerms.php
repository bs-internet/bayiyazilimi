<?php

declare(strict_types=1);

namespace App\Support\Ledger;

use App\Enums\Shop\LimitAction;
use App\Enums\Shop\PaymentTerms;
use App\Models\Account;
use App\Models\DealerGroup;
use App\Support\Money;
use App\Support\Payments\PaymentRegistry;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Bir hesabın geçerli cari çalışma şekli: bayiye özel değer, yoksa bayi
 * grubunun değeri, yoksa varsayılan. Bayi olmayan hesap (vitrin müşterisi)
 * her zaman peşin çalışır ve yöntem kısıtı yoktur.
 */
final readonly class CreditTerms
{
    public const DEFAULT_TERM_DAYS = 30;

    /**
     * @param  array<int, string>|null  $allowedMethods
     */
    public function __construct(
        public PaymentTerms $terms,
        public ?BigDecimal $limit,
        public int $termDays,
        public LimitAction $overLimit,
        public LimitAction $overdue,
        public ?array $allowedMethods,
    ) {}

    public static function for(?Account $account): self
    {
        if ($account === null || ! $account->isDealer()) {
            return new self(PaymentTerms::Prepaid, null, self::DEFAULT_TERM_DAYS, LimitAction::Approval, LimitAction::Approval, null);
        }

        $group = $account->dealerGroup;
        $pick = fn (string $key) => $account->getAttribute($key) ?? $group?->getAttribute($key);
        $limit = $pick('credit_limit');
        $methods = $pick('allowed_payment_methods');

        return new self(
            PaymentTerms::tryFrom((string) $pick('payment_terms')) ?? PaymentTerms::Prepaid,
            $limit !== null && $limit !== '' ? Money::round($limit) : null,
            (int) ($pick('payment_term_days') ?? self::DEFAULT_TERM_DAYS),
            LimitAction::tryFrom((string) $pick('over_limit_action')) ?? LimitAction::Approval,
            LimitAction::tryFrom((string) $pick('overdue_action')) ?? LimitAction::Approval,
            is_array($methods) && $methods !== [] ? array_values($methods) : null,
        );
    }

    public function isCredit(): bool
    {
        return $this->terms === PaymentTerms::Credit;
    }

    /** Vade tarihi (tek kural): verilen günden — varsayılan bugün — vade günü sonrası. */
    public function dueDate(?CarbonImmutable $from = null): CarbonImmutable
    {
        return ($from ?? CarbonImmutable::today())->addDays($this->termDays);
    }

    public function allowsMethod(string $key): bool
    {
        return $this->allowedMethods === null || in_array($key, $this->allowedMethods, true);
    }

    /**
     * Grup (zorunlu, varsayılanlı) ya da bayi (boş = gruptan) formu doğrulama kuralları.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $override): array
    {
        $req = $override ? 'nullable' : 'required';

        return [
            'payment_terms' => [$req, Rule::enum(PaymentTerms::class)],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'payment_term_days' => [$req, 'integer', 'min:0', 'max:3650'],
            'over_limit_action' => [$req, Rule::in([LimitAction::Approval->value, LimitAction::Block->value])],
            'overdue_action' => [$req, Rule::enum(LimitAction::class)],
            'allowed_payment_methods' => ['nullable', 'array'],
            'allowed_payment_methods.*' => ['string', 'max:24'],
        ];
    }

    /**
     * Form seçenekleri (bayi grubu ve bayi cari ayarları).
     *
     * @return array{terms: array<int, array{value: string, label: string}>, over_limit: array<int, array{value: string, label: string}>, overdue: array<int, array{value: string, label: string}>, methods: array<int, array{value: string, label: string}>}
     */
    public static function options(): array
    {
        return [
            'terms' => PaymentTerms::options(),
            'over_limit' => LimitAction::options(),
            'overdue' => LimitAction::options(LimitAction::cases()),
            'methods' => array_values(array_map(
                fn ($g) => ['value' => $g->key(), 'label' => $g->label()],
                app(PaymentRegistry::class)->all(),
            )),
        ];
    }

    /**
     * Kayıtlı ham değerler (bayi formunda boş = gruptan).
     *
     * @return array<string, mixed>
     */
    public static function raw(Account|DealerGroup|null $model): array
    {
        return [
            'payment_terms' => $model?->payment_terms,
            'credit_limit' => $model?->credit_limit !== null ? Money::str($model->credit_limit) : null,
            'payment_term_days' => $model?->payment_term_days,
            'over_limit_action' => $model?->over_limit_action,
            'overdue_action' => $model?->overdue_action,
            'allowed_payment_methods' => $model?->allowed_payment_methods,
        ];
    }

    /**
     * Formdan gelen değerler: boş metin → null, virgüllü sayı → nokta.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $limit = $input['credit_limit'] ?? null;
        $methods = $input['allowed_payment_methods'] ?? null;

        return [
            'credit_limit' => is_string($limit) ? (trim($limit) === '' ? null : str_replace(',', '.', $limit)) : $limit,
            'allowed_payment_methods' => is_array($methods) && $methods !== [] ? array_values(array_filter($methods, 'is_string')) : null,
            ...array_map(fn ($v) => $v === '' ? null : $v, array_intersect_key($input, array_flip(['payment_terms', 'payment_term_days', 'over_limit_action', 'overdue_action']))),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'payment_terms' => __('çalışma şekli'),
            'credit_limit' => __('kredi limiti'),
            'payment_term_days' => __('vade günü'),
            'over_limit_action' => __('limit aşımı'),
            'overdue_action' => __('vadesi geçmiş borç'),
            'allowed_payment_methods' => __('izinli ödeme yöntemleri'),
        ];
    }
}
