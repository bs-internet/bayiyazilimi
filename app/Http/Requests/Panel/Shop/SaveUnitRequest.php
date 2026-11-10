<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Unit|null $unit */
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:32', Rule::unique('units', 'name')->ignore($unit?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('birim adı'),
            'sort_order' => __('sıra'),
        ];
    }
}
