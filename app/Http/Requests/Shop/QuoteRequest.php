<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Http\Controllers\Shop\QuoteListController;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Oturum açmış üyede iletişim bilgileri formdan değil hesaptan gelir.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user() !== null) {
            $this->merge(QuoteListController::memberContact($this->user()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guest = $this->user() === null;

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => [$guest ? 'required' : 'nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            // Üyeler sözleşmeleri kayıtta kabul etti.
            'kvkk' => $guest ? ['accepted'] : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => __('ad soyad'),
            'company' => __('firma'),
            'email' => __('e-posta'),
            'phone' => __('telefon'),
            'city' => __('şehir'),
            'customer_note' => __('not'),
            'kvkk' => __('KVKK onayı'),
        ];
    }
}
