<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/stock: [{sku, warehouse, quantity}] — mutlak miktarlar.
 */
class SyncStockRequest extends FormRequest
{
    public const MAX_ITEMS = 500;

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
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['required', 'array'],
            'items.*.sku' => ['required', 'string', 'max:64'],
            'items.*.warehouse' => ['required', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => __('stok satırları'),
            'items.*.sku' => __('ürün kodu'),
            'items.*.warehouse' => __('depo kodu'),
            'items.*.quantity' => __('miktar'),
        ];
    }
}
