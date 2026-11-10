<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\Setting;
use App\Services\Panel\Settings\Modules\ModuleService;
use App\Support\BayiYazilimi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Güncelleme sonrası adımlar: yeni dosyalar yüklendikten / `git pull` ve
 * `composer install` yapıldıktan sonra veritabanını ve eklentilerin kurulum
 * adımlarını kodla aynı sürüme getirir. `bayi:update` komutu ve panelin
 * "Sistem güncellemesi bekliyor" uyarısı (Super Admin) bu servisi kullanır.
 *
 * Adımlar: (isteğe bağlı) veritabanı yedeği → migrate → kurulum adımı
 * bekleyen eklentiler için `bayi:install` → önbellek temizliği → kuyruk
 * işleyicisine yeniden başlama sinyali → kurulu sürüm kaydı.
 */
final class UpdateService
{
    /** Kodun hangi sürümüne göre güncellendiği (ayarlar tablosu). */
    public const VERSION_SETTING = 'installed_version';

    private const PENDING_CACHE = 'bayiyazilimi.update.pending';

    public function __construct(private readonly ModuleService $modules) {}

    /** Kurulu sürüm kodla aynı değilse ya da bir eklentinin kurulum adımı bekliyorsa. */
    public function pending(): bool
    {
        return Cache::remember(self::PENDING_CACHE, 300, fn (): bool => $this->installedVersion() !== BayiYazilimi::VERSION
            || $this->pendingModules() !== []);
    }

    public function installedVersion(): ?string
    {
        $version = Setting::get(self::VERSION_SETTING);

        return $version === null || $version === '' ? null : $version;
    }

    /** Kurulumdan sonra (sihirbaz) kod sürümünü kaydeder. */
    public function markCurrent(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => self::VERSION_SETTING],
            ['value' => BayiYazilimi::VERSION, 'group' => 'general'],
        );

        Cache::forget(self::PENDING_CACHE);
    }

    /**
     * Kurulum adımı çalışmamış (composer ile eklenmiş) ya da yeni sürümü gelmiş, açık eklentiler.
     *
     * @return list<string> eklenti anahtarları
     */
    public function pendingModules(): array
    {
        $pending = array_values(array_filter($this->modules->installed(), fn (array $module): bool => $module['enabled'] && $module['setup'] !== 'done'));

        // İhtiyaç duyulan eklenti önce kurulur (bayi:install, gerektirdiği eklenti kurulmadan çalışmaz).
        $ordered = [];
        while ($pending !== []) {
            $waiting = array_column($pending, 'name');
            $ready = array_filter($pending, fn (array $module): bool => array_intersect($module['requires'], $waiting) === []) ?: [$pending[0]];

            foreach ($ready as $index => $module) {
                $ordered[] = $module['key'];
                unset($pending[$index]);
            }
            $pending = array_values($pending);
        }

        return $ordered;
    }

    /**
     * Güncelleme adımlarını çalıştırır; her adım `$report` ile bildirilir.
     *
     * @param  callable(string): void  $report
     * @return list<string> yapılan adımların özetleri
     */
    public function run(callable $report, bool $backup = true): array
    {
        $done = [];
        $step = function (string $message) use ($report, &$done): void {
            $done[] = $message;
            $report($message);
        };

        if ($backup) {
            $file = $this->backupDatabase();
            $step($file !== null
                ? __('Veritabanı yedeği alındı: :file', ['file' => $file])
                : __('Veritabanı yedeği alınamadı (mysqldump bulunamadı); yedeği kendiniz alın.'));
        }

        Artisan::call('migrate', ['--force' => true]);
        $step(__('Veritabanı güncellendi.'));

        foreach ($this->pendingModules() as $key) {
            Artisan::call('bayi:install', ['module' => $key]);
            $step(__(':module eklentisinin kurulum adımı çalıştırıldı.', ['module' => $key]));
        }

        Artisan::call('optimize:clear');
        $step(__('Önbellekler temizlendi.'));

        Artisan::call('queue:restart');
        $step(__('Kuyruk işleyicisine yeniden başlama sinyali gönderildi.'));

        $this->markCurrent();
        $step(__('Sistem :version sürümüne güncellendi.', ['version' => BayiYazilimi::VERSION]));

        return $done;
    }

    /**
     * mysqldump ile `storage/app/private/backups/` altına SQL yedeği. Araç yoksa
     * ya da başarısızsa null (güncelleme durmaz; kullanıcı uyarılır).
     */
    public function backupDatabase(): ?string
    {
        /** @var array{host?: string, port?: int|string, database?: string, username?: string, password?: string} $db */
        $db = config('database.connections.'.config('database.default'), []);

        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            return null;
        }

        $dir = storage_path('app/private/backups');
        $relative = 'storage/app/private/backups/'.now()->format('Y-m-d-His').'.sql';
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
}
