<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCorporateInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'mersis_number' => ['nullable', 'string', 'max:32'],
            'store_email' => ['nullable', 'email', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:255'],
            'store_country_id' => ['required', 'uuid', Rule::exists('countries', 'id')],
            'store_city_id' => ['required', 'uuid', Rule::exists('cities', 'id')->where('country_id', $this->input('store_country_id'))],
            'store_district_id' => ['required', 'uuid', Rule::exists('districts', 'id')->where('city_id', $this->input('store_city_id'))],
            'store_address' => ['required', 'string', 'max:500'],
            'store_postcode' => ['nullable', 'string', 'max:16'],
            'etbis_url' => ['nullable', 'url:https', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_country_id' => __('ülke'),
            'store_city_id' => __('il'),
            'store_district_id' => __('ilçe'),
            'store_address' => __('açık adres'),
            'etbis_url' => __('ETBİS doğrulama bağlantısı'),
        ];
    }
}
