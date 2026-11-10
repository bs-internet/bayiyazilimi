<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\AddressType;
use App\Models\Account;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Models\User;
use App\Support\Shop\SystemDefaults;
use Illuminate\Support\Facades\DB;

/**
 * Üye adresleri (Adreslerim): kayıt, varsayılan seçimi ve ödeme ekranından kaydetme.
 *
 * Teslimat ve fatura adresleri kesin olarak ayrıdır: her kayıt tek bir türdedir,
 * varsayılan bayrağı da yalnızca kendi türü içindir (teslimat → is_default_shipping,
 * fatura → is_default_billing). Fatura bilgileri (firma, vergi, T.C. kimlik) yalnızca
 * fatura adresinde tutulur.
 */
class AddressService
{
    /** Yalnızca fatura adresinde anlamlı alanlar. */
    private const BILLING_FIELDS = ['company', 'tax_office', 'tax_number', 'identity_number'];

    /**
     * Adresi kaydeder; `$alsoAsOther` ise aynı bilgilerle diğer türde AYRI bir kayıt da açar
     * ("Bu adresi fatura adresi olarak da kaydet").
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Account $account, array $data, ?CustomerAddress $address = null, bool $alsoAsOther = false): CustomerAddress
    {
        return DB::transaction(function () use ($account, $data, $address, $alsoAsOther) {
            $saved = $this->persist($account, $data, $address);

            if ($alsoAsOther) {
                $type = $saved->type->other();
                $this->persist($account, [
                    ...$data,
                    'type' => $type,
                    'title' => $data['title'] ?? $saved->title,
                    // Yeni kaydın varsayılanlığı kendi türündeki ilk adres kuralına bırakılır.
                    'is_default_shipping' => false,
                    'is_default_billing' => false,
                ], null);
            }

            return $saved;
        });
    }

    public function setDefault(CustomerAddress $address): void
    {
        // Varsayılan bayrak adresin kendi türüne aittir; teslimat adresi fatura varsayılanı olamaz.
        $column = $address->type->defaultColumn();

        DB::transaction(function () use ($address, $column) {
            CustomerAddress::query()
                ->where('account_id', $address->account_id)
                ->where('type', $address->type->value)
                ->update([$column => false]);
            $address->forceFill([$column => true])->save();
        });
    }

    public function delete(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $column = $address->type->defaultColumn();
            $wasDefault = (bool) $address->{$column};
            $accountId = $address->account_id;
            $type = $address->type;

            $address->delete();

            if ($wasDefault) {
                $this->promoteDefault($accountId, $type);
            }
        });
    }

    /**
     * Ödeme ekranında "adreslerime kaydet" işaretlendiyse teslimat adresini teslimat,
     * fatura adresini fatura türünde (ayrı kayıtlar olarak) kaydeder. Aynı türde aynı
     * adres zaten kayıtlıysa tekrar eklemez.
     *
     * @param  array<string, mixed>  $billing
     * @param  array<string, mixed>|null  $shipping
     */
    public function saveFromCheckout(User $user, array $billing, ?array $shipping): void
    {
        $account = $user->account;

        if ($account === null) {
            return;
        }

        $entries = [[AddressType::Billing, __('Fatura adresi'), $billing]];

        if ($shipping !== null) {
            $entries[] = [AddressType::Shipping, __('Teslimat adresi'), $shipping];
        }

        foreach ($entries as [$type, $title, $data]) {
            $exists = CustomerAddress::query()
                ->where('account_id', $account->id)
                ->where('type', $type->value)
                ->where('address', $data['address'] ?? '')
                ->where('name', $data['name'] ?? '')
                ->exists();

            if ($exists) {
                continue;
            }

            [$cityId, $districtId] = $this->resolveLocation($data['city'] ?? null, $data['district'] ?? null);

            $this->save($account, [
                'title' => $title,
                'type' => $type,
                'name' => $data['name'] ?? $user->name,
                'company' => $data['company'] ?? null,
                'tax_office' => $data['tax_office'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'identity_number' => $data['identity_number'] ?? null,
                'phone' => $data['phone'] ?? null,
                'city_id' => $cityId,
                'district_id' => $districtId,
                'address' => $data['address'] ?? '',
                'postal_code' => $data['postal_code'] ?? null,
            ]);
        }
    }

    /**
     * İl/ilçe adlarından tanım kayıtlarını bulur (Türkiye).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function resolveLocation(?string $city, ?string $district): array
    {
        if (! $city) {
            return [null, null];
        }

        $cityModel = City::query()->where('name', $city)->first();
        $districtId = $cityModel && $district
            ? District::query()->where('city_id', $cityModel->id)->where('name', $district)->value('id')
            : null;

        return [$cityModel?->id, $districtId];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Account $account, array $data, ?CustomerAddress $address): CustomerAddress
    {
        $address ??= new CustomerAddress;

        // Düzenlemede eski tür ve varsayılanlık: tür değişirse eski türe varsayılan aktarılır.
        $oldType = $address->exists ? $address->type : null;
        $wasDefault = $oldType !== null && (bool) $address->{$oldType->defaultColumn()};

        $address->fill($data);
        $address->account_id = $account->id;
        $address->country_id ??= SystemDefaults::countryId();

        $type = $address->type;
        $column = $type->defaultColumn();
        $otherColumn = $type->other()->defaultColumn();

        // Varsayılan adres, işareti kaldırılarak varsayılansız bırakılamaz: başka bir
        // adres "varsayılan yap" ile seçilir (türünde her zaman bir varsayılan olur).
        if ($wasDefault && $oldType === $type) {
            $address->{$column} = true;
        }

        // Bir adres yalnızca kendi türünün varsayılanı olabilir.
        $address->{$otherColumn} = false;

        if ($type === AddressType::Shipping) {
            foreach (self::BILLING_FIELDS as $field) {
                $address->{$field} = null;
            }
        }

        // Türündeki ilk adres otomatik varsayılan olur.
        $hasSibling = CustomerAddress::query()
            ->where('account_id', $account->id)
            ->where('type', $type->value)
            ->when($address->exists, fn ($q) => $q->whereKeyNot($address->id))
            ->exists();

        if (! $hasSibling) {
            $address->{$column} = true;
        }

        $address->save();

        if ($address->{$column}) {
            CustomerAddress::query()
                ->where('account_id', $account->id)
                ->where('type', $type->value)
                ->whereKeyNot($address->id)
                ->update([$column => false]);
        }

        if ($wasDefault && $oldType !== $type) {
            $this->promoteDefault($account->id, $oldType);
        }

        return $address;
    }

    /** Türünde varsayılan kalmadıysa en eski adres varsayılan olur. */
    private function promoteDefault(string $accountId, AddressType $type): void
    {
        $column = $type->defaultColumn();
        $query = CustomerAddress::query()->where('account_id', $accountId)->where('type', $type->value);

        if ((clone $query)->where($column, true)->exists()) {
            return;
        }

        $query->oldest()->first()?->forceFill([$column => true])->save();
    }
}
