<?php

declare(strict_types=1);

namespace App\Http\Resources\Panel\Members;

use App\Models\User;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'last_login_at' => $this->last_login_at,
            'media_directory' => $this->media_directory,
            'created_at' => $this->created_at,
            'account_id' => $this->account_id,
            // Müşteri türü; hesabı henüz açılmamış üyede null — çağıran account.dealerGroup'u yükler.
            'customer_type' => $this->whenLoaded('account', fn () => $this->account?->type?->value, null),
            'company' => $this->whenLoaded('account', fn () => $this->account?->title),
            'dealer_group' => $this->whenLoaded('account', fn () => $this->account?->dealerGroup
                ? ['id' => $this->account->dealerGroup->id, 'name' => $this->account->dealerGroup->name, 'discount_rate' => $this->account->dealerGroup->discount_rate]
                : null),
            'discount_rate' => $this->whenLoaded('account', fn () => $this->account?->discount_rate !== null ? $this->account->discount_rate : null),
            'effective_discount_rate' => $this->whenLoaded('account', fn () => $this->account?->dealerDiscountRate() ?? Money::round(0)),
            'warehouse' => $this->whenLoaded('account', fn () => $this->account?->relationLoaded('warehouse') && $this->account->warehouse
                ? ['id' => $this->account->warehouse->id, 'name' => $this->account->warehouse->name]
                : null),
            // Cari çalışma şekli: bayiye özel ham değerler (boş = gruptan) ve geçerli olanın özeti.
            'credit_terms' => $this->whenLoaded('account', fn () => CreditTerms::raw($this->account)),
            'effective_terms' => $this->whenLoaded('account', fn () => $this->account ? (function (CreditTerms $t) {
                return [
                    'terms' => $t->terms->value,
                    'label' => $t->terms->label(),
                    'limit' => $t->limit,
                    'term_days' => $t->termDays,
                ];
            })($this->account->creditTerms()) : null),
            // Liste sorgusu (MemberService::paginate) ekler: ciro tanımına uyan sipariş sayısı / TL toplamı.
            'orders_count' => $this->when(array_key_exists('orders_count', $this->resource->getAttributes()), fn () => (int) $this->resource->getAttribute('orders_count')),
            'orders_total' => $this->when(array_key_exists('orders_total', $this->resource->getAttributes()), fn () => Money::round($this->resource->getAttribute('orders_total'))),
        ];
    }
}
