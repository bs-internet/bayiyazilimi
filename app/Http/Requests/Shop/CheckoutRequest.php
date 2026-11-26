<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

class CheckoutRequest extends FormRequest
{
    /** @var array<string, string>|null */
    private ?array $checkoutContracts = null;

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
            // Ödeme formunda onaylatılan her sözleşmenin kendi onay kutusu vardır.
            'agreements' => ['nullable', 'array'],
            ...collect($this->checkoutContracts())->mapWithKeys(fn (string $title, string $slug) => ["agreements.{$slug}" => ['accepted']])->all(),
            // Ticari elektronik ileti onayı (6563 s. Kanun): isteğe bağlı, ayrı kutu.
            'marketing_consent' => ['nullable', 'boolean'],
            'save_address' => ['nullable', 'boolean'],
            'billing_same_as_shipping' => ['boolean'],
            // Tek kullanımlık ödeme anahtarı ve ekranda görülen tutar (CheckoutGuard).
            'checkout_token' => ['nullable', 'string', 'max:64'],
            'expected_total' => ['nullable', 'numeric', 'min:0'],
            // Ziyaretçinin "bu bilgilerle üye ol" seçimi: şifre ve üyelik sözleşmeleri.
            ...($this->wantsAccount() ? [
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
                'account_agreements' => ['nullable', 'array'],
                ...collect($this->registrationContracts())->mapWithKeys(fn (string $title, string $slug) => ["account_agreements.{$slug}" => ['accepted']])->all(),
            ] : []),
        ];
    }

    /** Ziyaretçi ödeme formunda üye olmak istiyor mu (üyelik açıksa). */
    public function wantsAccount(): bool
    {
        return $this->user() === null && $this->boolean('create_account') && Features::enabled(Features::registration());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('Bu e-posta adresiyle üyeliğiniz var; giriş yaparak devam edin.'),
        ];
    }

    /**
     * Üyelikte onaylatılan, ödeme formunda henüz onaylatılmamış sözleşmeler: slug => başlık.
     *
     * @return array<string, string>
     */
    private function registrationContracts(): array
    {
        return array_diff_key(Contract::titlesForForm(Contract::REGISTRATION), $this->checkoutContracts());
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

        unset($data['agreements'], $data['save_address'], $data['billing_same_as_shipping'], $data['password'], $data['account_agreements']);

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
            'marketing_consent' => __('ticari ileti onayı'),
            'password' => __('şifre'),
            ...collect($this->registrationContracts())->mapWithKeys(fn (string $title, string $slug) => ["account_agreements.{$slug}" => $title])->all(),
            ...collect($this->checkoutContracts())->mapWithKeys(fn (string $title, string $slug) => ["agreements.{$slug}" => $title])->all(),
        ];
    }

    /**
     * Ödeme formunda onaylatılan aktif sözleşmeler: slug => başlık.
     *
     * @return array<string, string>
     */
    private function checkoutContracts(): array
    {
        return $this->checkoutContracts ??= Contract::titlesForForm(Contract::CHECKOUT);
    }
}
