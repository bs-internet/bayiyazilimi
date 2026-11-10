<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Status;
use App\Support\Shop\Storefront;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_image' => $this->boolean('remove_image'),
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        // Vitrin yokken SEO alanları formda yoktur; kayıtlı değerlere dokunulmaz.
        $data = $this->safe()->except(['image', 'remove_image', ...(Storefront::enabled() ? [] : ['meta_title', 'meta_description'])]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
