<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Install\Installer;
use App\Support\Install\InstallState;
use App\Support\Install\Requirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

/**
 * Kurulum sihirbazının komut satırı sürümü (VPS). Web sihirbazıyla aynı
 * Installer servisini kullanır.
 */
class SetupCommand extends Command
{
    protected $signature = 'bayi:setup';

    protected $description = 'Bayi Yazılımı kurulumu: veritabanı, temel / örnek veriler, yönetici hesabı.';

    public function handle(InstallState $state, Requirements $requirements, Installer $installer): int
    {
        if ($state->installed()) {
            $this->components->info('Sistem zaten kurulu.');

            return self::SUCCESS;
        }

        $failing = array_filter($requirements->check(), fn (array $check): bool => ! $check['ok']);

        if ($failing !== []) {
            $this->components->error('Eksik gereksinimler var:');
            $this->components->bulletList(array_map(fn (array $check): string => $check['label'], $failing));

            return self::FAILURE;
        }

        $this->configureDatabase($installer);

        $data = [
            'app_name' => text('Firma / site adı', required: true),
            'app_url' => text('Site adresi', default: (string) config('app.url'), required: true),
            'admin_name' => text('Yönetici adı', required: true),
            'admin_email' => text('Yönetici e-postası', required: true, validate: fn (string $value) => Validator::make(['e' => $value], ['e' => 'email'])->fails() ? 'Geçerli bir e-posta adresi girin.' : null),
            'admin_password' => password('Yönetici şifresi', required: true, hint: 'En az 12 karakter; büyük ve küçük harf, rakam ve sembol.', validate: fn (string $value) => Validator::make(['p' => $value], ['p' => [Installer::adminPasswordRule()]], [], ['p' => __('şifre')])->errors()->first() ?: null),
            'sample_data' => select('Başlangıç verileri', [
                'empty' => 'Boş yükle (yalnız temel veriler)',
                'sample' => 'Örnek verileri yükle (örnek ürünler, bayi hesabı, örnek sipariş)',
            ]) === 'sample',
        ];

        $admin = spin(fn () => $installer->install($data), 'Kuruluyor: tablolar ve veriler yükleniyor…');

        $this->newLine();
        $this->components->info("Kurulum tamamlandı. Giriş: {$admin->email}");
        $this->components->bulletList([
            'Ön yüz derlenmediyse: npm ci && npm run build',
            'Cron: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            'Kuyruk çalıştırıcı (supervisor): php artisan queue:work',
        ]);

        return self::SUCCESS;
    }

    /**
     * .env'deki bağlantı çalışıyorsa onu kullanır; değilse bilgileri sorar.
     */
    private function configureDatabase(Installer $installer): void
    {
        /** @var array<string, mixed> $mysql */
        $mysql = config('database.connections.mysql', []);
        $current = [
            'host' => (string) ($mysql['host'] ?? '127.0.0.1'),
            'port' => (string) ($mysql['port'] ?? '3306'),
            'database' => (string) ($mysql['database'] ?? ''),
            'username' => (string) ($mysql['username'] ?? ''),
            'password' => isset($mysql['password']) ? (string) $mysql['password'] : null,
        ];

        if ($installer->testDatabase($current) === null
            && confirm("Veritabanı: {$current['username']}@{$current['host']}/{$current['database']} kullanılsın mı?")) {
            $installer->saveDatabase($current);

            return;
        }

        while (true) {
            $db = [
                'host' => text('Veritabanı sunucusu', default: $current['host'], required: true),
                'port' => text('Port', default: $current['port'], required: true),
                'database' => text('Veritabanı adı', default: $current['database'], required: true),
                'username' => text('Kullanıcı adı', default: $current['username'], required: true),
                'password' => password('Şifre'),
            ];

            $error = $installer->testDatabase($db);

            if ($error === null) {
                $installer->saveDatabase($db);

                return;
            }

            $this->components->error($error);
            $current = [...$db, 'password' => null];
        }
    }
}
