<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/orders/{number}/invoice: fatura no (null ya da boş kaldırır).
 */
class RecordInvoiceRequest extends FormRequest
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
            'invoice_number' => ['present', 'nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'invoice_number' => __('fatura no'),
        ];
    }
}
