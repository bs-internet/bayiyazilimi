<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarrierRatesRequest extends FormRequest
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
            'rates' => ['array', 'max:500'],
            'rates.*.min_desi' => ['nullable', 'numeric', 'min:0'],
            'rates.*.max_desi' => ['required', 'numeric', 'min:0'],
            'rates.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rates.*.min_desi' => __('başlangıç desi'),
            'rates.*.max_desi' => __('bitiş desi'),
            'rates.*.price' => __('fiyat'),
        ];
    }
}
