<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sayım satırı: barkodla okutma (`mode=add`, miktar eklenir) ya da miktarı eşitleme (`mode=set`).
 */
class StockCountLineRequest extends FormRequest
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
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'location_id' => ['required', 'uuid', Rule::exists('warehouse_locations', 'id')],
            'lot_code' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'mode' => ['required', Rule::in(['add', 'set'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => __('ürün'),
            'location_id' => __('lokasyon'),
            'lot_code' => __('lot / seri no'),
            'expires_at' => __('son kullanma tarihi'),
            'quantity' => __('miktar'),
        ];
    }
}
