<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Account;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST API cari hesap gösterimi. `balance` ana para birimindedir; pozitif =
 * müşteri borçlu. Kredi bilgisi geçerli çalışma şeklinden (bayiye özel → grup
 * → varsayılan) gelir; tekil gösterimde kullanılabilir limit, risk ve vadesi
 * geçmiş borç da eklenir.
 *
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    private ?BigDecimal $balance = null;

    /** @var array{risk: BigDecimal, available: ?BigDecimal, overdue: array{amount: BigDecimal, count: int}}|null */
    private ?array $credit = null;

    public function withBalance(BigDecimal $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    /**
     * @param  array{risk: BigDecimal, available: ?BigDecimal, overdue: array{amount: BigDecimal, count: int}}  $credit
     */
    public function withCredit(array $credit): static
    {
        $this->credit = $credit;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Account $account */
        $account = $this->resource;
        $terms = CreditTerms::for($account);

        $credit = [
            'payment_terms' => $terms->terms->value,
            'limit' => $terms->limit === null ? null : (string) $terms->limit,
            'term_days' => $terms->termDays,
        ];

        if ($this->credit !== null) {
            $credit += [
                'risk' => (string) $this->credit['risk'],
                'available' => $this->credit['available'] === null ? null : (string) $this->credit['available'],
                'overdue_amount' => (string) Money::round($this->credit['overdue']['amount']),
                'overdue_count' => $this->credit['overdue']['count'],
            ];
        }

        $address = $account->relationLoaded('address') ? $account->address : null;

        return [
            'id' => $account->id,
            'code' => $account->code,
            'title' => $account->title,
            'type' => $account->type?->value,
            'billing_type' => $account->billing_type?->value,
            'tax_office' => $account->tax_office,
            'tax_number' => $account->tax_number,
            'identity_number' => $account->identity_number,
            'invoice_email' => $account->invoice_email,
            'dealer_group' => $account->dealerGroup?->name,
            'discount_rate' => $account->isDealer() ? (string) $account->dealerDiscountRate() : null,
            'address' => $address === null ? null : [
                'address' => $address->address,
                'postal_code' => $address->postal_code,
                'district' => $address->district?->name,
                'city' => $address->city?->name,
                'country' => $address->country?->name,
            ],
            'currency' => Money::baseCode(),
            'balance' => (string) Money::round($this->balance ?? 0),
            'credit' => $credit,
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Yüklenen ilişkiler (N+1 yok).
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['dealerGroup', 'address.city', 'address.district', 'address.country'];
    }
}
