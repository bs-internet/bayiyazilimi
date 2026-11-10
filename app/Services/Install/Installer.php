<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Setting;
use App\Models\User;
use App\Services\System\UpdateService;
use App\Support\Install\DatabaseErrorMessage;
use App\Support\Install\EnvironmentFile;
use App\Support\Install\InstallState;
use Database\Seeders\CoreDataSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Throwable;

/**
 * Kurulum adımları — web sihirbazı (InstallController) ve `bayi:setup`
 * komutu aynı servisi kullanır.
 *
 * 1. Veritabanı: bağlantı denenir, bilgiler `.env`'e yazılır.
 * 2. Kurulum: tablolar, temel veriler (her zaman), örnek veriler (seçilirse),
 *    site adı, Super Admin yöneticisi; `.env` canlı ortama alınır ve kurulum
 *    kilitlenir.
 */
final class Installer
{
    public function __construct(
        private readonly EnvironmentFile $env,
        private readonly InstallState $state,
    ) {}

    /**
     * Yönetici şifresi kuralı. Kurulum sırasında ortam henüz "local" olduğundan
     * Password::defaults() boş kalır; kural burada ortamdan bağımsız sabitlenir
     * (canlı ortamın kuralıyla aynı, sızıntı sorgusu hariç — kurulum sunucusu
     * dışarı çıkamayabilir).
     */
    public static function adminPasswordRule(): Password
    {
        return Password::min(12)->mixedCase()->letters()->numbers()->symbols();
    }

    /**
     * Bağlantıyı dener; başarılıysa null, değilse kullanıcıya gösterilecek
     * Türkçe hata iletisi. Başka bir uygulamanın tablolarını taşıyan (dolu,
     * ama bu sistemin `migrations` tablosu olmayan) veritabanı da reddedilir;
     * yoksa kurulum tabloların yarısını oluşturup yarıda kalır.
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     */
    public function testDatabase(array $db): ?string
    {
        config(['database.connections.install_check' => $this->connection($db)]);

        try {
            $connection = DB::connection('install_check');
            $connection->getPdo();

            $tables = array_column($connection->getSchemaBuilder()->getTables(), 'name');

            if ($tables !== [] && ! in_array('migrations', $tables, true)) {
                return __('Veritabanı boş değil: içinde başka tablolar var. Boş bir veritabanı seçin ya da içindeki tabloları silip yeniden deneyin.');
            }

            return null;
        } catch (Throwable $e) {
            return DatabaseErrorMessage::for($e, $db['database']);
        } finally {
            DB::purge('install_check');
        }
    }

    /**
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     */
    public function saveDatabase(array $db): void
    {
        $this->env->set([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
        ]);

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => $this->connection($db),
        ]);

        DB::purge('mysql');
    }

    /**
     * @param  array{app_name: string, app_url: string, admin_name: string, admin_email: string, admin_password: string, sample_data: bool}  $data
     */
    public function install(array $data): User
    {
        @set_time_limit(0);

        // Aynı anda iki kurulum (çift gönderim, iki sekme) migrate / seed'i yarıştırmasın.
        $lock = Cache::lock('bayiyazilimi:install', 3600);

        if (! $lock->get()) {
            throw new RuntimeException(__('Kurulum zaten sürüyor; lütfen bekleyin.'));
        }

        try {
            return $this->runInstall($data);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{app_name: string, app_url: string, admin_name: string, admin_email: string, admin_password: string, sample_data: bool}  $data
     */
    private function runInstall(array $data): User
    {
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => CoreDataSeeder::class, '--force' => true]);

        if ($data['sample_data']) {
            Artisan::call('db:seed', ['--class' => SampleDataSeeder::class, '--force' => true]);
        }

        Setting::query()->updateOrCreate(['key' => 'app_name'], ['value' => $data['app_name'], 'group' => 'general']);

        $admin = User::query()->updateOrCreate(
            ['email' => $data['admin_email']],
            [
                'name' => $data['admin_name'],
                'password' => Hash::make($data['admin_password']),
                'status' => UserStatus::Active,
                'user_type' => UserType::Admin,
                'locale' => 'tr',
                'timezone' => 'Europe/Istanbul',
            ],
        );
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->syncRoles(['Super Admin']);

        $this->linkStorage();

        $this->env->set([
            'APP_NAME' => $data['app_name'],
            'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            // Canlıda ayrıntılı (debug) kayıt diski doldurur ve iç bilgileri yazar.
            'LOG_LEVEL' => 'warning',
            // https kurulumda oturum çerezi yalnız şifreli bağlantıda gönderilir.
            'SESSION_SECURE_COOKIE' => str_starts_with(strtolower($data['app_url']), 'https://'),
        ]);

        // Pakette hazır gelen eklentiler (ör. kurulum paketindeki PayTR / iyzico) kurulumla birlikte kurulur.
        $updates = app(UpdateService::class);
        foreach ($updates->pendingModules() as $module) {
            Artisan::call('bayi:install', ['module' => $module]);
        }

        // Kurulan kod sürümü: sonraki güncellemelerde "güncelleme bekliyor" denetimi buna göre.
        $updates->markCurrent();

        $this->state->markInstalled();

        return $admin;
    }

    /**
     * public/storage bağlantısı (yüklenen görseller). Paylaşımlı hostingde
     * sembolik bağlantı kapalı olabilir; o durumda kurulum yine tamamlanır.
     */
    private function linkStorage(): void
    {
        if (app()->runningUnitTests() || file_exists(public_path('storage'))) {
            return;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
            // Görseller yüklenemezse panelden / komutla sonradan bağlanır.
        }
    }

    /**
     * @param  array{host: string, port: int|string, database: string, username: string, password: ?string}  $db
     * @return array<string, mixed>
     */
    private function connection(array $db): array
    {
        /** @var array<string, mixed> $base */
        $base = config('database.connections.mysql', []);

        return [
            ...$base,
            'host' => $db['host'],
            'port' => (string) $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => (string) ($db['password'] ?? ''),
            'url' => null,
        ];
    }
}
