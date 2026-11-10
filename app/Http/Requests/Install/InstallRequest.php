<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use App\Services\Install\Installer;
use Illuminate\Foundation\Http\FormRequest;

class InstallRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:100'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'confirmed', Installer::adminPasswordRule()],
            'sample_data' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'app_name' => __('firma / site adı'),
            'admin_name' => __('yönetici adı'),
            'admin_email' => __('yönetici e-postası'),
            'admin_password' => __('şifre'),
            'sample_data' => __('örnek veriler'),
        ];
    }
}
