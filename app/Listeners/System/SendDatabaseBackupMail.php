<?php

declare(strict_types=1);

namespace App\Listeners\System;

use App\Events\System\DatabaseBackedUpEvent;
use App\Notifications\System\DatabaseBackupNotification;
use App\Services\Shop\ShopSettings;
use App\Services\System\BackupService;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Alınan yedeği Genel Ayarlar › Yedekleme'deki adreslere (yoksa .env
 * BAYIYAZILIMI_BACKUP_MAIL_TO) şifreli (AES-256 zip) ek olarak gönderir. Şifre
 * yoksa yedek e-postayla hiç gönderilmez. Gönderim hatası yedeği bozmaz, yalnız raporlanır.
 */
class SendDatabaseBackupMail
{
    /** E-posta sunucularının ek sınırının güvenli altı. */
    public const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly BackupService $backups) {}

    public function handle(DatabaseBackedUpEvent $event): void
    {
        $addresses = self::addresses();

        if ($addresses === []) {
            return;
        }

        $password = self::password();

        if ($password === '') {
            report(new RuntimeException(__('Yedek e-postası gönderilmedi: zip şifresi boş (yedek şifresiz gönderilmez).')));

            return;
        }

        $archive = $this->backups->archive($event->path, $password);

        if ($archive === null) {
            report(new RuntimeException(__('Yedek e-postası gönderilmedi: yedek sıkıştırılamadı.')));

            return;
        }

        try {
            $attach = filesize($archive) <= self::MAX_ATTACHMENT_BYTES;

            Notification::route('mail', $addresses)->notify(
                new DatabaseBackupNotification(basename($event->path), (int) filesize($archive), $attach ? $archive : null),
            );
        } catch (Throwable $e) {
            report($e);
        } finally {
            @unlink($archive);
        }
    }

    /**
     * Yedeğin gideceği adresler: Genel Ayarlar › Yedekleme, boşsa BAYIYAZILIMI_BACKUP_MAIL_TO.
     *
     * @return list<string>
     */
    public static function addresses(): array
    {
        $value = trim((string) ShopSettings::get('backup_mail_to')) ?: (string) config('bayiyazilimi.backup.mail_to', '');

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /** Zip şifresi: Genel Ayarlar › Yedekleme, boşsa BAYIYAZILIMI_BACKUP_MAIL_PASSWORD. */
    public static function password(): string
    {
        return (string) (ShopSettings::get('backup_mail_password') ?: config('bayiyazilimi.backup.mail_password', ''));
    }
}
