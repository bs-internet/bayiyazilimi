<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\UpdateService;
use App\Support\Install\InstallState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Güncelleme sonrası tek komut: yeni dosyalar (git pull + composer install ya da
 * hazır paket) yerine konduktan sonra çalıştırılır. Bkz. UpdateService.
 */
class UpdateCommand extends Command
{
    protected $signature = 'bayi:update
        {--no-backup : Veritabanı yedeği alma}
        {--no-maintenance : Güncelleme sırasında siteyi bakıma alma}
        {--build : Ön yüzü de derle (npm ci && npm run build)}';

    protected $description = 'Bayi Yazılımı güncellemesini tamamlar: yedek, veritabanı, eklenti kurulum adımları, önbellek.';

    public function handle(InstallState $state, UpdateService $update): int
    {
        if (! $state->installed()) {
            $this->error('Sistem henüz kurulmamış. Önce php artisan bayi:setup çalıştırın.');

            return self::FAILURE;
        }

        $maintenance = ! $this->option('no-maintenance');

        if ($maintenance) {
            Artisan::call('down', ['--retry' => 60]);
            $this->components->info('Site bakım moduna alındı.');
        }

        try {
            if ($this->option('build')) {
                $this->components->task('Ön yüz derleniyor (npm ci && npm run build)', function (): bool {
                    return Process::timeout(1800)->path(base_path())->run('npm ci')->successful()
                        && Process::timeout(1800)->path(base_path())->run('npm run build')->successful();
                });
            }

            $update->run(fn (string $message) => $this->components->info($message), backup: ! $this->option('no-backup'));
        } catch (Throwable $e) {
            $this->error('Güncelleme tamamlanamadı: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if ($maintenance) {
                Artisan::call('up');
                $this->components->info('Site yeniden açıldı.');
            }
        }

        if (! $this->option('build')) {
            $this->comment('Ön yüz dosyaları değiştiyse: npm ci && npm run build (ya da --build ile çalıştırın).');
        }

        return self::SUCCESS;
    }
}
