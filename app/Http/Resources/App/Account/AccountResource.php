<?php

declare(strict_types=1);

namespace App\Http\Resources\App\Account;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'billing_type' => $this->billing_type?->value ?? 'individual',
            'tax_office' => $this->tax_office,
            'tax_number' => $this->tax_number,
            'identity_number' => $this->identity_number,
            'invoice_email' => $this->invoice_email,
            'address' => $this->address ? [
                'address' => $this->address->address,
                'postal_code' => $this->address->postal_code,
                'district_id' => $this->address->district_id,
                'city_id' => $this->address->city_id,
                'country_id' => $this->address->country_id,
            ] : null,
        ];
    }
}
