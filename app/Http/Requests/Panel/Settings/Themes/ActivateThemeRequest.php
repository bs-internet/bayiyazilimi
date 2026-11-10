<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Settings\Themes;

use Illuminate\Foundation\Http\FormRequest;

class ActivateThemeRequest extends FormRequest
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
        // Boş = varsayılan tema; dolu anahtar kurulu temalar arasında aranır (denetleyici).
        return [
            'theme' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'theme' => __('tema'),
        ];
    }
}
