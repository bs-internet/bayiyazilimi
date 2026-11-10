<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'same_address' => $this->boolean('same_address'),
            'save_address' => $this->boolean('save_address'),
            'billing_same_as_shipping' => $this->boolean('billing_same_as_shipping'),
        ]);

        // "Fatura adresim teslimat adresimle aynı": teslimat adresinin konum alanları
        // fatura bloğuna kopyalanır; sipariş yine iki ayrı adres bloğu saklar.
        if ($this->boolean('billing_same_as_shipping') && ! $this->boolean('same_address')) {
            $shipping = (array) $this->input('shipping', []);
            $billing = (array) $this->input('billing', []);

            foreach (['address', 'city', 'district', 'postal_code'] as $field) {
                $billing[$field] = $shipping[$field] ?? null;
            }

            $billing['name'] = ($billing['name'] ?? null) ?: ($shipping['name'] ?? null);

            $this->merge(['billing' => $billing]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $corporate = $this->input('billing.type') === 'corporate';
        $shipping = ! $this->boolean('same_address');

        return [
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:32'],

            'billing.type' => ['required', Rule::in(['individual', 'corporate'])],
            'billing.name' => ['required', 'string', 'max:255'],
            'billing.company' => [$corporate ? 'required' : 'nullable', 'string', 'max:255'],
            'billing.tax_office' => [$corporate ? 'required' : 'nullable', 'string', 'max:100'],
            'billing.tax_number' => [$corporate ? 'required' : 'nullable', 'string', 'max:20'],
            'billing.identity_number' => ['nullable', 'digits:11'],
            'billing.address' => ['required', 'string', 'max:500'],
            'billing.city' => ['required', 'string', 'max:100'],
            'billing.district' => ['required', 'string', 'max:100'],
            'billing.postal_code' => ['nullable', 'string', 'max:16'],

            'same_address' => ['boolean'],
            'shipping.name' => [$shipping ? 'required' : 'nullable', 'string', 'max:255'],
            'shipping.phone' => ['nullable', 'string', 'max:32'],
            'shipping.address' => [$shipping ? 'required' : 'nullable', 'string', 'max:500'],
            'shipping.city' => [$shipping ? 'required' : 'nullable', 'string', 'max:100'],
            'shipping.district' => [$shipping ? 'required' : 'nullable', 'string', 'max:100'],
            'shipping.postal_code' => ['nullable', 'string', 'max:16'],

            'payment_method' => ['required', 'string', 'max:24'],
            'carrier_id' => ['nullable', 'uuid', Rule::exists('carriers', 'id')->where('status', 'active')],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            // Ödeme formunda onaylatılan sözleşme yoksa onay kutusu da yoktur.
            'agreement' => Contract::query()->active()->where('show_on_checkout', true)->exists() ? ['accepted'] : ['nullable'],
            'save_address' => ['nullable', 'boolean'],
            'billing_same_as_shipping' => ['boolean'],
            // Tek kullanımlık ödeme anahtarı ve ekranda görülen tutar (CheckoutGuard).
            'checkout_token' => ['nullable', 'string', 'max:64'],
            'expected_total' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Servisin beklediği yapı (OrderService::placeFromCart / placeFromQuote).
     *
     * @return array<string, mixed>
     */
    public function orderData(): array
    {
        $data = $this->validated();
        $data['billing']['phone'] = $data['phone'];

        if (! $data['same_address']) {
            $data['shipping']['phone'] = ($data['shipping']['phone'] ?? null) ?: $data['phone'];
        }

        unset($data['agreement'], $data['save_address'], $data['billing_same_as_shipping']);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('e-posta'),
            'phone' => __('telefon'),
            'billing.name' => __('ad soyad'),
            'billing.company' => __('firma ünvanı'),
            'billing.tax_office' => __('vergi dairesi'),
            'billing.tax_number' => __('vergi numarası'),
            'billing.identity_number' => __('T.C. kimlik no'),
            'billing.address' => __('adres'),
            'billing.city' => __('il'),
            'billing.district' => __('ilçe'),
            'shipping.name' => __('teslim alacak kişi'),
            'shipping.address' => __('teslimat adresi'),
            'shipping.city' => __('teslimat ili'),
            'shipping.district' => __('teslimat ilçesi'),
            'payment_method' => __('ödeme yöntemi'),
            'carrier_id' => __('kargo firması'),
            'agreement' => __('sözleşme onayı'),
        ];
    }
}
