<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Shop;

use App\Enums\Shop\AddressType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default_shipping' => $this->boolean('is_default_shipping'),
            'is_default_billing' => $this->boolean('is_default_billing'),
            'also_as_other' => $this->boolean('also_as_other'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Firma / vergi / T.C. kimlik yalnızca fatura adresinde; teslimat adresinde yok sayılır.
        $billing = $this->input('type') === AddressType::Billing->value ? 'nullable' : 'exclude';

        return [
            'title' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(AddressType::class)],
            'name' => ['required', 'string', 'max:255'],
            'company' => [$billing, 'string', 'max:255'],
            'tax_office' => [$billing, 'required_with:company', 'string', 'max:100'],
            'tax_number' => [$billing, 'required_with:company', 'string', 'max:20'],
            'identity_number' => [$billing, 'digits:11'],
            'phone' => ['required', 'string', 'min:10', 'max:32'],
            'city_id' => ['required', 'uuid', Rule::exists('cities', 'id')],
            'district_id' => ['required', 'uuid', Rule::exists('districts', 'id')->where('city_id', $this->input('city_id'))],
            'address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'is_default_shipping' => ['boolean'],
            'is_default_billing' => ['boolean'],
            // "Bu adresi fatura (teslimat) adresi olarak da kaydet" → diğer türde ayrı kayıt.
            'also_as_other' => ['boolean'],
        ];
    }

    /**
     * Modele yazılacak alanlar (yardımcı bayraklar hariç).
     *
     * @return array<string, mixed>
     */
    public function addressData(): array
    {
        return collect($this->validated())->except('also_as_other')->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('adres başlığı'),
            'type' => __('adres türü'),
            'name' => __('ad soyad'),
            'company' => __('firma ünvanı'),
            'tax_office' => __('vergi dairesi'),
            'tax_number' => __('vergi numarası'),
            'identity_number' => __('T.C. kimlik no'),
            'phone' => __('telefon'),
            'city_id' => __('il'),
            'district_id' => __('ilçe'),
            'address' => __('adres'),
        ];
    }
}
