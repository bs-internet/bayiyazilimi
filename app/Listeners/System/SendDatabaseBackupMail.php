<?php

declare(strict_types=1);

namespace App\Listeners\System;

use App\Events\System\DatabaseBackedUpEvent;
use App\Notifications\System\DatabaseBackupNotification;
use App\Services\System\BackupService;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Alınan yedeği `bayiyazilimi.backup.mail_to` adreslerine şifreli (AES-256 zip)
 * ek olarak gönderir. Şifre (`BAYIYAZILIMI_BACKUP_MAIL_PASSWORD`) yoksa yedek
 * e-postayla hiç gönderilmez. Gönderim hatası yedeği bozmaz, yalnız raporlanır.
 */
class SendDatabaseBackupMail
{
    /** E-posta sunucularının ek sınırının güvenli altı. */
    public const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly BackupService $backups) {}

    public function handle(DatabaseBackedUpEvent $event): void
    {
        $addresses = array_values(array_filter(array_map('trim', explode(',', (string) config('bayiyazilimi.backup.mail_to', '')))));

        if ($addresses === []) {
            return;
        }

        $password = (string) config('bayiyazilimi.backup.mail_password', '');

        if ($password === '') {
            report(new RuntimeException(__('Yedek e-postası gönderilmedi: BAYIYAZILIMI_BACKUP_MAIL_PASSWORD boş (yedek şifresiz gönderilmez).')));

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
}
