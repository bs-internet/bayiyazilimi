<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Services\Panel\Settings\Roles\RoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Genel Ayarlar › Yedekleme. Yedek tüm veritabanıdır: gideceği adresi ve zip
 * şifresini yalnız Super Admin değiştirir (yetkisi olan diğer yöneticiler görür).
 */
class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleService::SUPER_ADMIN) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'backup_mail_to' => ['nullable', 'string', 'max:500'],
            // Boş gönderilirse kayıtlı şifre korunur (gizli ayar).
            'backup_mail_password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_filter(array_map('trim', explode(',', (string) $this->input('backup_mail_to')))) as $address) {
                    if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                        $validator->errors()->add('backup_mail_to', __(':address geçerli bir e-posta adresi değil.', ['address' => $address]));

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'backup_mail_to' => __('e-posta adresleri'),
            'backup_mail_password' => __('zip şifresi'),
        ];
    }
}
