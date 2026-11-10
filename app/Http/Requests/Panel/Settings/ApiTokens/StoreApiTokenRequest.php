<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Settings\ApiTokens;

use App\Support\Api\ApiAbilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in(ApiAbilities::grantableBy($this->user()))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('anahtar adı'),
            'abilities' => __('yetkiler'),
            'abilities.*' => __('yetki'),
            'expires_at' => __('geçerlilik sonu'),
        ];
    }
}
