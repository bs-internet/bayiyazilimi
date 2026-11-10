<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;

class InstallDatabaseRequest extends FormRequest
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
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'host' => __('sunucu'),
            'port' => __('port'),
            'database' => __('veritabanı adı'),
            'username' => __('kullanıcı adı'),
            'password' => __('şifre'),
        ];
    }
}
