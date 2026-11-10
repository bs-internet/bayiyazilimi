<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Shop\CustomerType;
use App\Support\Ledger\CreditTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('discount_rate'))) {
            $this->merge(['discount_rate' => str_replace(',', '.', $this->input('discount_rate'))]);
        }

        $this->merge(CreditTerms::normalize($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerType::class)->only(CustomerType::available())],
            'dealer_group_id' => ['nullable', 'uuid', Rule::exists('dealer_groups', 'id')],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'warehouse_id' => ['nullable', 'uuid', Rule::exists('warehouses', 'id')],
            ...CreditTerms::rules(override: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('müşteri türü'),
            'dealer_group_id' => __('bayi grubu'),
            'discount_rate' => __('bayiye özel indirim'),
            'warehouse_id' => __('bölge deposu'),
            ...CreditTerms::attributes(),
        ];
    }
}
