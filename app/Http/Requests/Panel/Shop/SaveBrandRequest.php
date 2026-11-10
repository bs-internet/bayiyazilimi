<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->safe()->except(['logo', 'remove_logo']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
