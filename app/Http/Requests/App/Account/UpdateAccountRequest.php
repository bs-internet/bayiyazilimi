<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Account;

use App\Enums\BillingType;
use App\Enums\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'billing_type' => __('fatura türü'),
            'title' => __('ad soyad / firma ünvanı'),
            'identity_number' => __('TC kimlik no'),
            'tax_office' => __('vergi dairesi'),
            'tax_number' => __('vergi no'),
            'invoice_email' => __('e-fatura e-posta adresi'),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'billing_type' => ['required', Rule::enum(BillingType::class)],
            'title' => ['required', 'string', 'max:255'],
            'identity_number' => ['nullable', 'required_if:billing_type,individual', 'digits:11'],
            'tax_office' => ['nullable', 'required_if:billing_type,corporate', 'string', 'max:100'],
            'tax_number' => ['nullable', 'required_if:billing_type,corporate', 'digits_between:10,11'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'country_id' => [
                'nullable',
                'required_with:city_id,district_id',
                'uuid',
                Rule::exists('countries', 'id')->where('status', Status::Active->value),
            ],
            'city_id' => [
                'nullable',
                'required_with:district_id',
                'uuid',
                Rule::exists('cities', 'id')
                    ->where('country_id', $this->string('country_id')->toString())
                    ->where('status', Status::Active->value),
            ],
            'district_id' => [
                'nullable',
                'uuid',
                Rule::exists('districts', 'id')
                    ->where('city_id', $this->string('city_id')->toString())
                    ->where('status', Status::Active->value),
            ],
        ];
    }
}
