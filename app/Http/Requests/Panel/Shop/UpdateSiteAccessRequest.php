<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['maintenance_mode' => $this->boolean('maintenance_mode')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'maintenance_mode' => ['boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
