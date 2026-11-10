<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InstalledModule;
use App\Models\Permission;
use App\Support\Modules\ModuleDiscovery;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleState;
use App\Support\Registry\PermissionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bir modülü kurar: module.json'a göre ön yüz/mail dosyalarını publish eder,
 * migration'ları çalıştırır, yetkileri DB'ye seed eder (role atamadan) ve varsa
 * modülün Installer::installed() kancasını çağırır.
 *
 * Akış: composer require → bayi:install → npm run build.
 */
final class InstallModuleCommand extends Command
{
    protected $signature = 'bayi:install {module : Eklenti anahtarı (module.json key)} {--force : Var olan dosyaların üzerine yaz}';

    protected $description = 'Bir Bayi Yazılımı eklentisini kurar (publish + migrate + yetki seed).';

    public function handle(ModuleDiscovery $discovery, PermissionRegistry $permissions, ModuleState $state): int
    {
        $key = (string) $this->argument('module');
        $manifest = $discovery->find($key);

        if ($manifest === null) {
            $this->error("Eklenti bulunamadı: {$key}. Önce `composer require` ile kurun.");

            return self::FAILURE;
        }

        // Kapalı modülün provider'ı yüklenmez: migration'ları da yüklenmez, kurulum
        // "tamamlandı" görünür ama tablolar oluşmazdı.
        if (! $state->isEnabled($manifest->key)) {
            $this->error("{$manifest->name} eklentisi kapalı. Önce Ayarlar → Eklentiler ekranından açın.");

            return self::FAILURE;
        }

        if (! $this->requirementsMet($manifest, $discovery)) {
            return self::FAILURE;
        }

        $this->info("Kuruluyor: {$manifest->name} v{$manifest->version}");

        $this->detectConflicts($manifest, $permissions);
        $this->publishFiles($manifest);

        if ($manifest->migrate) {
            $this->components->task('Migration çalıştırılıyor', function (): void {
                Artisan::call('migrate', ['--force' => true]);
            });
        }

        $this->seedPermissions($key, $permissions);
        $this->runInstaller($manifest, 'installed');
        $this->recordInstallation($manifest);

        $this->newLine();
        $this->info("✓ {$manifest->name} kuruldu.");
        $this->comment('Ön yüz dosyalarını derlemek için: npm run build');

        return self::SUCCESS;
    }

    /**
     * `requires` denetimi: PHP sürümü (alt sınır) ve ihtiyaç duyulan modüller
     * (composer ile yüklü ve `bayi:install` edilmiş). Laravel / bayiyazilimi
     * sürümleri composer.json'daki gerçek bağımlılıklarla zaten denetlenir.
     */
    private function requirementsMet(ModuleManifest $manifest, ModuleDiscovery $discovery): bool
    {
        $php = $manifest->requires['php'] ?? null;

        if (is_string($php) && preg_match('/(\d+\.\d+(?:\.\d+)?)/', $php, $m) === 1 && version_compare(PHP_VERSION, $m[1], '<')) {
            $this->error("{$manifest->name} PHP {$php} gerektiriyor (kurulu: ".PHP_VERSION.').');

            return false;
        }

        $missing = array_filter(
            $manifest->moduleDependencies(),
            fn (string $key): bool => $discovery->find($key) === null || ! InstalledModule::query()->where('key', $key)->exists(),
        );

        if ($missing !== []) {
            $this->error("{$manifest->name} için önce şu eklentiler kurulmalı: ".implode(', ', $missing).'.');

            return false;
        }

        return true;
    }

    /**
     * Kurulu modüller kaydı (Modüller ekranı). Yeniden kurulum (yeni sürüm)
     * sürümü günceller, ilk kurulum tarihini korur.
     */
    private function recordInstallation(ModuleManifest $manifest): void
    {
        $record = InstalledModule::query()->firstOrNew(['key' => $manifest->key]);

        $record->fill([
            'name' => $manifest->name,
            'version' => $manifest->version,
            'installed_at' => $record->installed_at ?? now(),
        ])->save();
    }

    private function detectConflicts(ModuleManifest $manifest, PermissionRegistry $permissions): void
    {
        $names = collect($permissions->forSource($manifest->key))->pluck('name');
        $existing = Permission::withTrashed()->whereIn('name', $names)->pluck('name');

        foreach ($existing as $name) {
            $this->warn("Yetki zaten mevcut, atlanacak: {$name}");
        }
    }

    private function publishFiles(ModuleManifest $manifest): void
    {
        $base = $manifest->basePath();

        if ($base === null) {
            return;
        }

        foreach ($manifest->publish as $entry) {
            $from = $this->safePath($base, $entry['from']);
            $to = $this->safePath(base_path(), $entry['to']);

            if ($from === null || $to === null) {
                $this->warn("Geçersiz yol atlandı: {$entry['from']} → {$entry['to']}");

                continue;
            }

            if (! File::exists($from)) {
                $this->warn("Kaynak yok, atlandı: {$entry['from']}");

                continue;
            }

            if (File::exists($to) && ! $this->option('force')
                && ! $this->confirm("Hedef var: {$entry['to']} — üzerine yazılsın mı?", false)) {
                $this->line("Atlandı: {$entry['to']}");

                continue;
            }

            if (File::isDirectory($from)) {
                File::ensureDirectoryExists($to);
                File::copyDirectory($from, $to);
            } else {
                File::ensureDirectoryExists(dirname($to));
                File::copy($from, $to);
            }

            $this->line("Publish: {$entry['to']}");
        }
    }

    private function seedPermissions(string $key, PermissionRegistry $permissions): void
    {
        $created = 0;

        foreach ($permissions->forSource($key) as $permission) {
            $exists = Permission::withTrashed()->where('name', $permission['name'])->exists();

            if ($exists) {
                continue;
            }

            Permission::query()->create([
                'name' => $permission['name'],
                'guard_name' => 'web',
                'group' => $permission['group'],
                'label' => $permission['label'],
            ]);

            $created++;
        }

        if ($created > 0) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->line("Yetki seed edildi: {$created} adet (rollere atanmadı).");
        }
    }

    private function runInstaller(ModuleManifest $manifest, string $method): void
    {
        $class = $this->installerClass($manifest);

        if ($class === null || ! method_exists($class, $method)) {
            return;
        }

        app()->call([app($class), $method]);
        $this->line("Installer::{$method}() çalıştırıldı.");
    }

    private function installerClass(ModuleManifest $manifest): ?string
    {
        $namespace = Str::beforeLast($manifest->provider, '\\');
        $class = $namespace.'\\'.Str::studly($manifest->key).'Installer';

        return class_exists($class) ? $class : null;
    }

    /**
     * Göreli yolu kök altında güvenle çözer; kök dışına (`../`) çıkarsa null.
     */
    private function safePath(string $root, string $relative): ?string
    {
        $resolved = $root.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalized = str_replace('\\', '/', $resolved);

        if (! str_starts_with($normalized, $normalizedRoot.'/')) {
            return null;
        }

        return $resolved;
    }
}
