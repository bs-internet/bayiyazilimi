<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * REST API ürün ekleme. Kategori ve marka slug ile, para birimi koduyla
 * verilir; gönderilmeyen alanlar panelin yeni ürün varsayılanlarını alır.
 * Stok bu uçtan değil PUT /stock ile yazılır.
 */
class StoreProductRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'model_number' => ['nullable', 'string', 'max:128'],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')],
            'brand' => ['nullable', 'string', Rule::exists('brands', 'slug')],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:100000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['nullable', 'string', Rule::exists('currencies', 'code')],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'sale_mode' => ['nullable', Rule::enum(SaleMode::class)],
            'status' => ['nullable', Rule::enum(Status::class)],
            'unit' => ['nullable', 'string', 'max:32', Rule::in(array_column(Unit::options(), 'value'))],
            'track_stock' => ['nullable', 'boolean'],
            'tracking' => ['nullable', Rule::enum(StockTracking::class)],
            'min_qty' => ['nullable', 'integer', 'min:1'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'desi' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => __('stok kodu'),
            'name' => __('ürün adı'),
            'barcode' => __('barkod'),
            'category' => __('kategori'),
            'brand' => __('marka'),
            'price' => __('fiyat'),
            'compare_price' => __('karşılaştırma fiyatı'),
            'currency' => __('para birimi'),
            'tax_rate' => __('KDV oranı'),
            'unit' => __('birim'),
        ];
    }
}
