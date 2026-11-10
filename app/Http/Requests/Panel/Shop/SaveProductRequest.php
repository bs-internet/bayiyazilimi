<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockTracking;
use App\Enums\Status;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['track_stock', 'allow_backorder', 'free_shipping', 'expiry_tracking'] as $key) {
            $this->merge([$key => $this->boolean($key)]);
        }

        $this->merge(['tracking' => $this->input('tracking') ?: StockTracking::None->value]);

        foreach (['price', 'compare_price', 'desi'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => str_replace(',', '.', $this->input($key))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            // Modüllerin ürün formuna eklediği alanlar (`shop.products.rules`).
            ...hooks()->apply('shop.products.rules', [], request: $this),
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:64'],
            'model_number' => ['nullable', 'string', 'max:128'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string'],
            'notice' => ['nullable', 'string', 'max:500'],
            'highlights' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['required', 'uuid', 'exists:currencies,id'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'sale_mode' => ['required', Rule::enum(SaleMode::class)],
            'track_stock' => ['boolean'],
            'allow_backorder' => ['boolean'],
            'tracking' => ['required', Rule::enum(StockTracking::class)],
            'expiry_tracking' => ['boolean'],
            // Boş: Stok Ayarları'ndaki genel kritik stok eşiği.
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'min_qty' => ['required', 'integer', 'min:1'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'delivery_days' => ['required', 'integer', 'min:0', 'max:365'],
            'desi' => ['nullable', 'numeric', 'min:0'],
            // Tanımlamalar → Birimler'deki aktif birimler (ürünün mevcut birimi de geçerli).
            'unit' => ['required', 'string', 'max:32', Rule::in(array_column(Unit::options($this->route('product')?->unit), 'value'))],
            'free_shipping' => ['boolean'],
            'status' => ['required', Rule::enum(Status::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'search_keywords' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key === null) {
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
            $data['desi'] = Money::round((string) ($data['desi'] ?? 0));
        }

        return $data;
    }
}
