<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'prices_include_tax' => $this->boolean('prices_include_tax'),
            'is_default' => $this->boolean('is_default'),
            'remove_logo' => $this->boolean('remove_logo'),
            'extra_desi_price' => str_replace(',', '.', (string) ($this->input('extra_desi_price') ?: '0')),
            'delivery_days' => $this->input('delivery_days') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('carrier')?->id;

        return [
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('carriers', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'extra_desi_price' => ['required', 'numeric', 'min:0'],
            'prices_include_tax' => ['boolean'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_default' => ['boolean'],
            'status' => ['required', Rule::enum(Status::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
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
