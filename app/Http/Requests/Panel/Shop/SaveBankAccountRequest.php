<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iban' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('iban'))),
            'bank_name' => trim((string) $this->input('bank_name')),
            'account_holder' => trim((string) $this->input('account_holder')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['required', 'string', 'max:255'],
            'iban' => ['required', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/'],
            'account_number' => ['nullable', 'string', 'max:32'],
            'currency_code' => ['nullable', 'string', Rule::exists('currencies', 'code')],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bank_name' => __('banka adı'),
            'branch' => __('şube'),
            'account_holder' => __('hesap sahibi'),
            'iban' => __('IBAN'),
            'account_number' => __('hesap no'),
            'currency_code' => __('para birimi'),
            'sort_order' => __('sıra'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iban.regex' => __('Geçerli bir IBAN girin (ör. TR12 0006 4000 0011 2345 6789 01).'),
        ];
    }
}
