<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Support\Payments\CardDetails;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Kart ekranındaki "Ödeme Yap" formu. Alanlar doğrulama hatasında oturuma
 * yazılmaz (bootstrap/app.php `dontFlash`).
 */
class ChargeCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $month = (string) $this->input('card_month');
        $year = (string) $this->input('card_year');

        // Ekrandaki tek alan: "AA/YY".
        if ($this->filled('card_expiry') && ! $this->filled('card_month')) {
            [$month, $year] = array_pad(explode('/', (string) $this->input('card_expiry'), 2), 2, '');
        }

        $year = (string) preg_replace('/\D/', '', $year);
        // "07" → "7" (integer kuralı baştaki sıfırı kabul etmez).
        $month = (string) preg_replace('/\D/', '', $month);
        $month = $month === '' ? '' : (string) (int) $month;

        $this->merge([
            'card_holder' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->input('card_holder'))),
            'card_number' => preg_replace('/\D/', '', (string) $this->input('card_number')),
            'card_month' => $month,
            // İki haneli yıl (26) dört haneye çevrilir.
            'card_year' => strlen($year) === 2 ? '20'.$year : $year,
            'card_cvv' => preg_replace('/\D/', '', (string) $this->input('card_cvv')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $year = (int) CarbonImmutable::now()->format('Y');

        return [
            'card_holder' => ['required', 'string', 'min:3', 'max:60'],
            'card_number' => ['required', 'digits_between:13,19'],
            'card_month' => ['required', 'integer', 'between:1,12'],
            'card_year' => ['required', 'integer', 'between:'.$year.','.($year + 20)],
            'card_cvv' => ['required', 'digits_between:3,4'],
            'installment' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! CardDetails::luhn((string) $this->input('card_number'))) {
                    $validator->errors()->add('card_number', __('Kart numarası geçersiz; lütfen kontrol edin.'));
                }

                $now = CarbonImmutable::now();

                if ((int) $this->input('card_year') === (int) $now->format('Y') && (int) $this->input('card_month') < (int) $now->format('n')) {
                    $validator->errors()->add('card_month', __('Kartın son kullanma tarihi geçmiş.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'card_holder' => __('kart üzerindeki ad soyad'),
            'card_number' => __('kart numarası'),
            'card_month' => __('son kullanma ayı'),
            'card_year' => __('son kullanma yılı'),
            'card_cvv' => __('güvenlik kodu (CVV)'),
            'installment' => __('taksit'),
        ];
    }

    /** Doğrulanmış kart bilgisi; taksit sürücünün sınırına indirilir. */
    public function card(int $maxInstallment): CardDetails
    {
        return new CardDetails(
            holder: (string) $this->input('card_holder'),
            number: (string) $this->input('card_number'),
            expiryMonth: (int) $this->input('card_month'),
            expiryYear: (int) $this->input('card_year'),
            cvv: (string) $this->input('card_cvv'),
            installment: min($maxInstallment, max(1, (int) $this->input('installment', 1))),
        );
    }
}
