<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Shop;

use App\Events\Shop\CatalogChangedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\Shop\UpdateBackupSettingsRequest;
use App\Listeners\System\SendDatabaseBackupMail;
use App\Services\Panel\Settings\Roles\RoleService;
use App\Services\Shop\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Genel Ayarlar › Yedekleme: günlük veritabanı yedeğinin şifreli zip olarak
 * gönderileceği adresler (ShopSettings::BACKUP_KEYS). Şifre gizli ayardır; ekrana
 * yalnız tanımlı olup olmadığı gelir.
 */
class BackupSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('panel/settings/general/backup', [
            'mailTo' => (string) ShopSettings::get('backup_mail_to'),
            'passwordSet' => (string) ShopSettings::get('backup_mail_password') !== '',
            // .env'de tanımlıysa ayar boşken o kullanılır.
            'envMailTo' => (string) config('bayiyazilimi.backup.mail_to', ''),
            'envPasswordSet' => (string) config('bayiyazilimi.backup.mail_password', '') !== '',
            'active' => SendDatabaseBackupMail::addresses() !== [] && SendDatabaseBackupMail::password() !== '',
            'at' => (string) config('bayiyazilimi.backup.at'),
            'keep' => (int) config('bayiyazilimi.backup.keep'),
            'canEdit' => $request->user()?->hasRole(RoleService::SUPER_ADMIN) === true,
        ]);
    }

    public function update(UpdateBackupSettingsRequest $request): RedirectResponse
    {
        ShopSettings::save([
            'backup_mail_to' => implode(', ', array_filter(array_map('trim', explode(',', (string) $request->validated('backup_mail_to'))))),
            'backup_mail_password' => (string) $request->validated('backup_mail_password'),
        ]);

        CatalogChangedEvent::dispatch(null, 'settings', 'updated', __('yedekleme ayarlarını güncelledi.'), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('Yedekleme ayarları kaydedildi.')]);
    }
}
