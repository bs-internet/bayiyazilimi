<?php

declare(strict_types=1);

use App\Models\InstalledModule;
use App\Models\Setting;
use App\Models\User;
use App\Services\System\UpdateService;
use App\Support\Install\DatabaseErrorMessage;
use App\Support\Install\EnvironmentFile;
use App\Support\Install\InstallState;
use App\Support\Install\Requirements;
use App\Support\Modules\ModuleState;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bayi-install-'.uniqid();
    mkdir($dir);
    $this->envPath = $dir.DIRECTORY_SEPARATOR.'.env';
    file_put_contents($this->envPath, "APP_NAME=Laravel\nAPP_ENV=local\nAPP_DEBUG=true\nDB_HOST=127.0.0.1\n");

    $this->app->instance(EnvironmentFile::class, new EnvironmentFile($this->envPath));
});

afterEach(function () {
    @unlink($this->envPath);
    @rmdir(dirname($this->envPath));
});

/** Sistemi "kurulmamış" yapar (testler varsayılan olarak kurulu çalışır). */
function notInstalled(): void
{
    config(['bayiyazilimi.installed' => false]);
    app()->instance(InstallState::class, new InstallState(app(EnvironmentFile::class)));
}

/**
 * .env'deki bağlantı henüz çalışmıyor (yeni kurulum). Test veritabanı MySQL olduğundan
 * sihirbaz onu "hazır" sayardı; yalnız sihirbazın denediği ayarlar bozulur, açık test
 * bağlantısı etkilenmez.
 */
function noDatabaseYet(): void
{
    config(['database.connections.mysql.port' => 1]);
}

test('every page leads to the installer until the system is installed', function () {
    notInstalled();
    noDatabaseYet();

    $this->get('/panel')->assertRedirect('/kurulum');
    $this->get('/login')->assertRedirect('/kurulum');

    $this->get('/kurulum')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('install/index')
            ->has('requirements')
            ->has('passes')
            ->where('database_ready', false)
            ->has('database.host'));
});

test('the installer is closed once the system is installed', function () {
    $this->get('/kurulum')->assertNotFound();
    $this->post('/kurulum', [])->assertNotFound();
});

test('a database that cannot be reached is reported', function () {
    notInstalled();

    $this->from('/kurulum')
        ->post('/kurulum/veritabani', [
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'yok',
            'username' => 'yok',
            'password' => 'yok',
        ])
        ->assertRedirect('/kurulum')
        ->assertSessionHasErrors(['database' => 'Veritabanı sunucusuna ulaşılamadı. Sunucu adresini ve portu kontrol edin; MySQL / MariaDB çalışıyor olmalı.']);

    expect(file_get_contents($this->envPath))->not->toContain('DB_DATABASE=yok');
});

test('database errors are shown as plain Turkish messages', function (string $raw, string $expected) {
    expect(DatabaseErrorMessage::for(new PDOException($raw), 'bayi'))->toBe($expected);
})->with([
    'wrong password' => [
        "SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)",
        'Kullanıcı adı ya da şifre hatalı. Veritabanı kullanıcısının adını ve şifresini kontrol edin.',
    ],
    'missing database' => [
        "SQLSTATE[HY000] [1049] Unknown database 'bayi'",
        '"bayi" adında bir veritabanı bulunamadı. Önce boş bir veritabanı oluşturun ya da adını kontrol edin.',
    ],
    'server down' => [
        'SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it',
        'Veritabanı sunucusuna ulaşılamadı. Sunucu adresini ve portu kontrol edin; MySQL / MariaDB çalışıyor olmalı.',
    ],
    'tables already there' => [
        "SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'addresses' already exists (Connection: mysql, SQL: create table `addresses` (...))",
        'Veritabanı boş değil: içinde başka tablolar var. Boş bir veritabanı seçin ya da içindeki tabloları silip yeniden deneyin.',
    ],
    'unknown error keeps a short detail without SQL' => [
        'SQLSTATE[HY000]: General error: 9999 Something odd (Connection: mysql, SQL: select 1)',
        'Veritabanı işlemi başarısız oldu. Bilgileri kontrol edip yeniden deneyin. (Ayrıntı: SQLSTATE[HY000]: General error: 9999 Something odd)',
    ],
]);

test('non-database installer errors pass through unchanged', function () {
    expect(DatabaseErrorMessage::for(new RuntimeException('Kurulum zaten sürüyor; lütfen bekleyin.')))
        ->toBe('Kurulum zaten sürüyor; lütfen bekleyin.');
});

test('the installer loads core data, creates the admin and locks itself', function () {
    notInstalled();
    $this->mock(Requirements::class, fn ($mock) => $mock->shouldReceive('passes')->andReturn(true));

    // Veritabanı adımı tamamlanmış (oturum bayrağı); kurulum test veritabanına yapılır.
    $this->withSession(['install.database' => true])->post('/kurulum', [
        'app_name' => 'Örnek Ticaret',
        'admin_name' => 'Ayşe Yılmaz',
        'admin_email' => 'ayse@ornek.com',
        'admin_password' => 'Guclu-Sifre-2026',
        'admin_password_confirmation' => 'Guclu-Sifre-2026',
        'sample_data' => '0',
    ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('install/done')
            ->where('admin_email', 'ayse@ornek.com'));

    $admin = User::query()->where('email', 'ayse@ornek.com')->firstOrFail();
    $env = (string) file_get_contents($this->envPath);

    expect($admin->hasRole('Super Admin'))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Setting::query()->where('key', 'app_name')->value('value'))->toBe('Örnek Ticaret')
        ->and(app(InstallState::class)->installed())->toBeTrue()
        ->and($env)->toContain('BAYIYAZILIMI_INSTALLED=true')
        ->and($env)->toContain('APP_NAME="Örnek Ticaret"')
        ->and($env)->toContain('APP_ENV=production')
        ->and($env)->toContain('APP_DEBUG=false')
        ->and($env)->toContain('LOG_LEVEL=warning');
});

test('modules shipped in the package are set up during installation', function () {
    notInstalled();
    $this->mock(Requirements::class, fn ($mock) => $mock->shouldReceive('passes')->andReturn(true));
    // Pakette gelen iki eklenti; demo-addon demo'ya ihtiyaç duyar (önce demo kurulmalı).
    $statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bayi-install-modules-'.uniqid().'.json';
    $this->app->instance(ModuleState::class, new ModuleState($statePath));
    config(['bayiyazilimi.module_discovery_paths' => [base_path('tests/Fixtures/Modules/*/module.json')]]);

    expect(app(UpdateService::class)->pendingModules())->toBe(['demo', 'demo-addon']);

    $this->withSession(['install.database' => true])->post('/kurulum', [
        'app_name' => 'Örnek Ticaret',
        'admin_name' => 'Ayşe Yılmaz',
        'admin_email' => 'ayse@ornek.com',
        'admin_password' => 'Guclu-Sifre-2026',
        'admin_password_confirmation' => 'Guclu-Sifre-2026',
        'sample_data' => '0',
    ])->assertOk();

    expect(InstalledModule::query()->pluck('key')->sort()->values()->all())->toBe(['demo', 'demo-addon'])
        ->and(app(UpdateService::class)->pending())->toBeFalse();

    @unlink($statePath);
});

test('the admin password must be strong even though the installer runs in local mode', function (string $password) {
    notInstalled();

    $this->withSession(['install.database' => true])->post('/kurulum', [
        'app_name' => 'Örnek Ticaret',
        'admin_name' => 'Ayşe Yılmaz',
        'admin_email' => 'ayse@ornek.com',
        'admin_password' => $password,
        'admin_password_confirmation' => $password,
        'sample_data' => '0',
    ])->assertSessionHasErrors('admin_password');
})->with(['password', 'kisaSifre1!', 'uzunsifre-2026', 'UzunSifreSembolsuz2026']);

test('the installer does not start before the database step', function () {
    notInstalled();
    noDatabaseYet();
    $this->mock(Requirements::class, fn ($mock) => $mock->shouldReceive('passes')->andReturn(true));

    $this->post('/kurulum', [
        'app_name' => 'Örnek Ticaret',
        'admin_name' => 'Ayşe Yılmaz',
        'admin_email' => 'ayse@ornek.com',
        'admin_password' => 'Guclu-Sifre-2026',
        'admin_password_confirmation' => 'Guclu-Sifre-2026',
        'sample_data' => '0',
    ])->assertRedirect(route('install.show'));

    expect(User::query()->where('email', 'ayse@ornek.com')->exists())->toBeFalse();
});

test('a database with users but no super admin (half-finished install) does not count as installed', function () {
    config(['bayiyazilimi.installed' => null]);
    User::query()->each(fn (User $user) => $user->syncRoles([]));

    expect((new InstallState(app(EnvironmentFile::class)))->installed())->toBeFalse();
});

test('installer input is validated', function () {
    notInstalled();

    $this->post('/kurulum', ['admin_email' => 'gecersiz', 'admin_password' => 'kisa'])
        ->assertSessionHasErrors(['app_name', 'admin_name', 'admin_email', 'admin_password', 'sample_data']);
});

test('the environment file keeps other lines and quotes values when needed', function () {
    $env = new EnvironmentFile($this->envPath);

    $env->set(['APP_NAME' => 'Bayi Yazılımı', 'DB_HOST' => 'db.local', 'DB_PASSWORD' => 'p@ss$word"x', 'NEW_KEY' => true]);

    expect(file_get_contents($this->envPath))->toBe(implode("\n", [
        'APP_NAME="Bayi Yazılımı"',
        'APP_ENV=local',
        'APP_DEBUG=true',
        'DB_HOST=db.local',
        'DB_PASSWORD="p@ss\$word\"x"',
        'NEW_KEY=true',
    ])."\n");
});

test('the environment file opens commented example lines in place instead of appending', function () {
    file_put_contents($this->envPath, "DB_CONNECTION=mysql\n# DB_HOST=127.0.0.1\n#DB_DATABASE=laravel\n\nSESSION_DRIVER=database\n");

    (new EnvironmentFile($this->envPath))->set(['DB_HOST' => 'localhost', 'DB_DATABASE' => 'bayi']);

    expect(file_get_contents($this->envPath))->toBe("DB_CONNECTION=mysql\nDB_HOST=localhost\nDB_DATABASE=bayi\n\nSESSION_DRIVER=database\n");
});

test('an existing system without the install flag counts as installed and gets the flag', function () {
    config(['bayiyazilimi.installed' => null]);
    $state = new InstallState(app(EnvironmentFile::class));

    expect($state->installed())->toBeTrue()
        ->and(file_get_contents($this->envPath))->toContain('BAYIYAZILIMI_INSTALLED=true');
});
test('the setup command does nothing on an installed system', function () {
    $this->artisan('bayi:setup')->expectsOutputToContain('Sistem zaten kurulu.')->assertSuccessful();
});
