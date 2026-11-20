<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\System\DatabaseBackedUpEvent;
use App\Services\System\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Günlük veritabanı yedeği (routes/console.php): `storage/app/private/backups`
 * altına SQL dosyası; en yeni `bayiyazilimi.backup.keep` yedek tutulur.
 * Yedek alınamazsa hata raporlanır (canlıda hata bildirimi e-postası gider).
 */
final class BackupCommand extends Command
{
    protected $signature = 'bayi:backup {--keep= : Tutulacak yedek sayısı (varsayılan: BAYIYAZILIMI_BACKUP_KEEP)}';

    protected $description = 'Veritabanının yedeğini alır ve eski yedekleri temizler.';

    public function handle(BackupService $backups): int
    {
        $file = $backups->database();

        if ($file === null) {
            $message = __('Veritabanı yedeği alınamadı: sunucuda mysqldump yok ya da çalışmadı. Yedeği hosting panelinden alın ya da mysqldump kurun.');

            report(new RuntimeException($message));
            $this->error($message);

            return self::FAILURE;
        }

        $keep = (int) ($this->option('keep') ?? config('bayiyazilimi.backup.keep', 14));
        $deleted = $backups->prune($keep);

        $this->info(__('Veritabanı yedeği alındı: :file', ['file' => $file]));

        // Yedeğin e-postayla gönderimi (BAYIYAZILIMI_BACKUP_MAIL_TO): SendDatabaseBackupMail.
        event(new DatabaseBackedUpEvent(storage_path(BackupService::DIRECTORY.DIRECTORY_SEPARATOR.basename($file))));

        if ($deleted > 0) {
            $this->line(__(':count eski yedek silindi.', ['count' => $deleted]));
        }

        return self::SUCCESS;
    }
}
