<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Api\ExternalLedgerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/accounts/{code}/transactions: dış sistemden tahsilat ya da dekont.
 * `reference` dış sistemin belge numarasıdır; aynı referans tekrar gelirse
 * mevcut kayıt döner.
 */
class StoreAccountTransactionRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(ExternalLedgerService::TYPES))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'reference' => ['required', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('hareket türü'),
            'amount' => __('tutar'),
            'reference' => __('referans'),
            'date' => __('tarih'),
            'due_date' => __('vade tarihi'),
            'description' => __('açıklama'),
        ];
    }
}
