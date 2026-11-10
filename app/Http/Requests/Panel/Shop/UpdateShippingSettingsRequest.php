<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['shipping_fee', 'free_shipping_threshold', 'default_product_desi'] as $key) {
            $merge[$key] = str_replace(',', '.', (string) ($this->input($key) ?: '0'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_fee' => ['required', 'numeric', 'min:0'],
            'free_shipping_threshold' => ['required', 'numeric', 'min:0'],
            'default_product_desi' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipping_fee' => __('sabit kargo ücreti'),
            'free_shipping_threshold' => __('ücretsiz kargo eşiği'),
            'default_product_desi' => __('varsayılan ürün desisi'),
        ];
    }
}
