<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\Shop\AddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Üyenin kayıtlı adresi (Adreslerim). Hesap (Account) bazlıdır; ödeme
 * ekranında fatura/teslimat adresi olarak seçilir.
 *
 * @property AddressType $type
 */
#[Fillable([
    'title',
    'type',
    'name',
    'company',
    'tax_office',
    'tax_number',
    'identity_number',
    'phone',
    'country_id',
    'city_id',
    'district_id',
    'address',
    'postal_code',
    'is_default_shipping',
    'is_default_billing',
])]
class CustomerAddress extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Sipariş adres JSON'u biçiminde (OrderService fatura/teslimat yapısı).
     *
     * @return array<string, string|null>
     */
    public function toOrderAddress(): array
    {
        return [
            'type' => $this->company ? 'corporate' : 'individual',
            'name' => $this->name,
            'company' => $this->company,
            'tax_office' => $this->tax_office,
            'tax_number' => $this->tax_number,
            'identity_number' => $this->identity_number,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city?->name,
            'district' => $this->district?->name,
            'postal_code' => $this->postal_code,
        ];
    }
}
