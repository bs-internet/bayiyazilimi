<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_sellable' => $this->boolean('is_sellable'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('warehouse')?->id;

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('warehouses', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_sellable' => ['boolean'],
            'status' => ['required', Rule::enum(Status::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
