<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\Shop\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/orders/{number}/status. Geçiş kuralını OrderService denetler
 * (OrderStatus::allowedTargets); geçersiz geçiş 422 + Türkçe mesaj döner.
 */
class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
            'notify' => ['sometimes', 'boolean'],
            'carrier' => ['sometimes', 'nullable', 'string', Rule::exists('carriers', 'code')],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('durum'),
            'note' => __('not'),
            'notify' => __('müşteriye bildir'),
            'carrier' => __('kargo firması'),
            'tracking_number' => __('takip numarası'),
        ];
    }
}
