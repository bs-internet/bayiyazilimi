<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/products/{sku}: panelin ürün formunun güvenli skaler alanları.
 * Gönderilmeyen alan değişmez.
 */
class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999'],
            'compare_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'tax_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'required', Rule::enum(Status::class)],
            'min_qty' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_qty' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'delivery_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:365'],
            'min_stock' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('ürün adı'),
            'barcode' => __('barkod'),
            'price' => __('fiyat'),
            'compare_price' => __('karşılaştırma fiyatı'),
            'tax_rate' => __('KDV oranı'),
            'status' => __('durum'),
            'min_qty' => __('en az sipariş adedi'),
            'max_qty' => __('en çok sipariş adedi'),
            'delivery_days' => __('teslim süresi'),
            'min_stock' => __('kritik stok'),
        ];
    }
}
