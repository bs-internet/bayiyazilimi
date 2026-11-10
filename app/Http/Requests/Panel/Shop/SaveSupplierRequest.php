<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Supplier|null $supplier */
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('tedarikçi adı'),
            'tax_office' => __('vergi dairesi'),
            'tax_number' => __('vergi / TC no'),
            'phone' => __('telefon'),
            'email' => __('e-posta'),
            'address' => __('adres'),
            'note' => __('not'),
        ];
    }
}
