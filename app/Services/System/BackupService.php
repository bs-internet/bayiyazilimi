<?php

declare(strict_types=1);

namespace App\Services\System;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Veritabanı yedeği: mysqldump ile `storage/app/private/backups/` altına SQL
 * dosyası. Günlük yedeği zamanlayıcıdaki `bayi:backup`, güncelleme öncesi
 * yedeği `bayi:update` (UpdateService) alır. Klasör web'den erişilemez
 * (private disk; dosya adresi verilmez).
 */
final class BackupService
{
    public const DIRECTORY = 'app/private/backups';

    /**
     * Yedek alır; yolunu (proje köküne göre) döndürür. Araç yoksa ya da
     * başarısızsa null.
     */
    public function database(): ?string
    {
        /** @var array{host?: string, port?: int|string, database?: string, username?: string, password?: string} $db */
        $db = config('database.connections.'.config('database.default'), []);

        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            return null;
        }

        $dir = storage_path(self::DIRECTORY);
        $relative = 'storage/'.self::DIRECTORY.'/'.now()->format('Y-m-d-His').'.sql';
        $path = base_path($relative);

        try {
            if (! is_dir($dir)) {
                mkdir($dir, 0750, true);
            }

            $result = Process::timeout(600)
                ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
                ->run([
                    'mysqldump',
                    '--single-transaction',
                    '--skip-lock-tables',
                    '--host='.($db['host'] ?? '127.0.0.1'),
                    '--port='.($db['port'] ?? '3306'),
                    '--user='.($db['username'] ?? 'root'),
                    '--result-file='.$path,
                    (string) ($db['database'] ?? ''),
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $result->successful() || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            return null;
        }

        return $relative;
    }

    /**
     * En yeni `$keep` yedek kalır, eskiler silinir. Silinen dosya sayısını döndürür.
     */
    public function prune(int $keep): int
    {
        $files = glob(storage_path(self::DIRECTORY).DIRECTORY_SEPARATOR.'*.sql') ?: [];

        // Dosya adı tarih-saattir (Y-m-d-His): ada göre sıralama tarihe göre sıralamadır.
        rsort($files);

        $deleted = 0;

        foreach (array_slice($files, max(1, $keep)) as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
