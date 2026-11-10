<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Support\Ledger\CreditTerms;
use Illuminate\Foundation\Http\FormRequest;

class SaveDealerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('discount_rate'))) {
            $this->merge(['discount_rate' => str_replace(',', '.', $this->input('discount_rate'))]);
        }

        $terms = CreditTerms::normalize($this->all());

        // Grup her zaman bir çalışma şekline sahiptir; gönderilmeyen alan varsayılanı alır.
        $this->merge([
            ...$terms,
            'payment_terms' => $terms['payment_terms'] ?? 'prepaid',
            'payment_term_days' => $terms['payment_term_days'] ?? CreditTerms::DEFAULT_TERM_DAYS,
            'over_limit_action' => $terms['over_limit_action'] ?? 'approval',
            'overdue_action' => $terms['overdue_action'] ?? 'approval',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            ...CreditTerms::rules(override: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('grup adı'),
            'discount_rate' => __('indirim oranı'),
            'description' => __('açıklama'),
            ...CreditTerms::attributes(),
        ];
    }
}
