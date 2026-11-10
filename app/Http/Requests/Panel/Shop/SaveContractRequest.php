<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Http\Middleware\SiteAccessGate;
use Illuminate\Foundation\Http\FormRequest;

class SaveContractRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'show_on_checkout' => ['boolean'],
            'show_on_registration' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Durum formdan değil, listedeki anahtardan değişir (Tanımlamalar kuralı).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['show_on_checkout'] = $this->boolean('show_on_checkout');
        // Üyelik formu yalnız site herkese açıkken (vitrin) vardır; yoksa kutu
        // formda yoktur ve kayıtlı değere dokunulmaz.
        if (SiteAccessGate::isPublic()) {
            $data['show_on_registration'] = $this->boolean('show_on_registration');
        } else {
            unset($data['show_on_registration']);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('başlık'),
            'slug' => __('kısa ad'),
            'content' => __('metin'),
            'sort_order' => __('sıra'),
        ];
    }
}
