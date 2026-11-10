<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Settings\Modules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleStatusRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'enabled' => __('eklenti durumu'),
        ];
    }
}
