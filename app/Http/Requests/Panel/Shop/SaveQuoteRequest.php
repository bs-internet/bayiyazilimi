<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\UserType;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panelde teklif kaydı (yeni teklif ya da talep düzenleme). `send=1` ile kayıttan
 * sonra müşteriye gönderilir; gönderimde tüm kalemlere fiyat girilmiş olmalı.
 */
class SaveQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(fn ($row) => [
            'product_id' => $row['product_id'] ?? null,
            'quantity' => $row['quantity'] ?? null,
            'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
            'tax_rate' => str_replace(',', '.', (string) ($row['tax_rate'] ?? '')),
            'unit_price' => ($price = trim((string) ($row['unit_price'] ?? ''))) === '' ? null : str_replace(',', '.', $price),
        ])->values()->all();

        $this->merge([
            'items' => $items,
            'shipping_total' => str_replace(',', '.', (string) ($this->input('shipping_total') ?: '0')),
            'valid_from' => $this->input('valid_from') ?: null,
            'valid_until' => $this->input('valid_until') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Quote|null $quote */
        $quote = $this->route('quote');
        // Müşteri, yöneticinin hazırladığı teklifte seçilir (talep edilen teklifte talep edendir).
        $needsCustomer = $quote === null || $quote->isAdminCreated();
        $send = $this->boolean('send');

        return [
            'user_id' => [$needsCustomer ? 'required' : 'nullable', 'uuid', Rule::exists('users', 'id')->where('user_type', UserType::Member->value)],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => array_filter(['nullable', 'date', 'after_or_equal:valid_from', $send ? 'after_or_equal:today' : null]),
            'shipping_total' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'uuid', 'distinct', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => [$send ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('müşteri'),
            'currency_code' => __('para birimi'),
            'admin_note' => __('müşteriye not'),
            'shipping_total' => __('kargo ücreti'),
            'items' => __('ürünler'),
            'items.*.product_id' => __('ürün'),
            'items.*.quantity' => __('miktar'),
            'items.*.unit_price' => __('teklif fiyatı'),
            'items.*.unit' => __('birim'),
            'items.*.tax_rate' => __('KDV oranı'),
            'valid_from' => __('geçerlilik başlangıcı'),
            'valid_until' => __('geçerlilik bitişi'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('Teklife en az bir ürün ekleyin.'),
            'items.min' => __('Teklife en az bir ürün ekleyin.'),
            'items.*.product_id.distinct' => __('Aynı ürün teklifte birden fazla kez var.'),
            'items.*.unit_price.required' => __('Göndermeden önce fiyat girin.'),
        ];
    }
}
