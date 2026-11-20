<?php

declare(strict_types=1);

use App\Events\System\ErrorReportedEvent;
use App\Http\Middleware\BindCurrentAccount;
use App\Mail\System\DatabaseBackupMail;
use App\Models\User;
use App\Notifications\System\DatabaseBackupNotification;
use App\Notifications\System\ErrorReportedNotification;
use App\Services\System\BackupService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

/*
 * Canlı ortam işletimi: hata bildirimi, günlük yedek, zamanlayıcı ve hesap
 * kapsamının rota bağlamasından önce kurulması.
 */

// ------------------------------------------------------------------ Hata bildirimi

test('a reported error notifies the active super admins once per throttle window', function () {
    config(['bayiyazilimi.error_alerts.enabled' => true]);
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();

    // Aynı yerde iki kez oluşan aynı hata (aynı tür + dosya + satır).
    foreach ([1, 2] as $attempt) {
        report(new RuntimeException('Ödeme sağlayıcısına bağlanılamadı'));
    }

    Notification::assertSentToTimes($admin, ErrorReportedNotification::class, 1);
    Notification::assertSentTo($admin, ErrorReportedNotification::class, function (ErrorReportedNotification $n, array $channels) {
        return $n->error->type === RuntimeException::class
            && str_contains($n->error->message, 'bağlanılamadı')
            && str_starts_with($n->error->location, 'tests')
            && $channels === ['mail', 'database'];
    });
});

test('error alerts go to the configured addresses instead of the admins', function () {
    config(['bayiyazilimi.error_alerts.enabled' => true, 'bayiyazilimi.error_alerts.to' => 'teknik@ornek.com, nobetci@ornek.com']);
    Notification::fake();
    $admin = User::factory()->superAdmin()->create();

    report(new LogicException('Beklenmeyen durum'));

    Notification::assertNotSentTo($admin, ErrorReportedNotification::class);
    Notification::assertSentOnDemand(ErrorReportedNotification::class, function (ErrorReportedNotification $n, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routeNotificationFor('mail') === ['teknik@ornek.com', 'nobetci@ornek.com'] && $channels === ['mail'];
    });
});

test('error alerts are off outside production unless enabled', function () {
    Event::fake([ErrorReportedEvent::class]);

    report(new RuntimeException('Yerel hata'));

    Event::assertNotDispatched(ErrorReportedEvent::class);
});

test('an alert that cannot be sent never breaks the request', function () {
    // Bildirim gönderilemese (posta sunucusu yok) de raporlama hata fırlatmaz.
    config(['bayiyazilimi.error_alerts.enabled' => true]);
    Event::listen(ErrorReportedEvent::class, fn () => throw new RuntimeException('SMTP kapalı'));

    expect(fn () => report(new RuntimeException('Asıl hata')))->not->toThrow(Throwable::class);
});

// ------------------------------------------------------------------ Yedek

function backupFiles(): array
{
    return glob(storage_path(BackupService::DIRECTORY).DIRECTORY_SEPARATOR.'*.sql') ?: [];
}

/** Yedek testleri geçici bir storage klasöründe çalışır (gerçek yedeklere dokunulmaz). */
function useTemporaryStorage(): string
{
    $previous = app()->storagePath();
    $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bayi-backup-'.uniqid();
    mkdir($tmp.DIRECTORY_SEPARATOR.BackupService::DIRECTORY, 0750, true);
    app()->useStoragePath($tmp);

    return $previous;
}

function restoreStorage(string $previous): void
{
    $tmp = app()->storagePath();
    app()->useStoragePath($previous);

    foreach (glob($tmp.DIRECTORY_SEPARATOR.BackupService::DIRECTORY.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
}

test('the backup command dumps the database and keeps only the newest backups', function () {
    $previous = useTemporaryStorage();
    $dir = storage_path(BackupService::DIRECTORY);
    @mkdir($dir, 0750, true);
    foreach (['2000-01-01-000000', '2000-01-02-000000', '2000-01-03-000000'] as $old) {
        file_put_contents($dir.DIRECTORY_SEPARATOR.$old.'.sql', '-- eski');
    }

    Process::fake(function ($process) {
        $file = collect($process->command)->first(fn (string $arg) => str_starts_with($arg, '--result-file='));
        file_put_contents(substr((string) $file, strlen('--result-file=')), '-- dump');

        return Process::result();
    });

    $this->artisan('bayi:backup', ['--keep' => 2])->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command[0] === 'mysqldump');

    $names = array_map('basename', backupFiles());
    restoreStorage($previous);

    expect($names)->toHaveCount(2)
        ->and($names)->toContain('2000-01-03-000000.sql')
        ->and($names)->not->toContain('2000-01-01-000000.sql');
});

test('a failed backup is reported so the admins hear about it', function () {
    $previous = useTemporaryStorage();
    config(['bayiyazilimi.error_alerts.enabled' => true]);
    Event::fake([ErrorReportedEvent::class]);
    Process::fake(fn () => Process::result(errorOutput: 'mysqldump: command not found', exitCode: 127));

    $this->artisan('bayi:backup')->assertFailed();
    restoreStorage($previous);

    Event::assertDispatched(ErrorReportedEvent::class, fn (ErrorReportedEvent $e) => str_contains($e->message, 'yedeği alınamadı'));
});

test('the scheduler runs the daily backup and drains the queue', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('bayi:backup')
        ->expectsOutputToContain('queue:work --stop-when-empty')
        ->assertSuccessful();
});

// ------------------------------------------------------------------ Hesap kapsamı

test('the member account scope is bound before route models are resolved', function () {
    $route = app('router')->getRoutes()->getByName('app.shop.orders.show');
    $middleware = array_map(fn (string $m) => explode(':', $m)[0], app('router')->gatherRouteMiddleware($route));

    expect(array_search(BindCurrentAccount::class, $middleware, true))
        ->toBeLessThan(array_search(SubstituteBindings::class, $middleware, true));
});

test('the daily backup is mailed as an encrypted zip when an address and password are set', function () {
    $previous = useTemporaryStorage();
    config(['bayiyazilimi.backup.mail_to' => 'yedek@example.com', 'bayiyazilimi.backup.mail_password' => 'gizli-sifre']);
    Notification::fake();
    Process::fake(function ($process) {
        $file = collect($process->command)->first(fn (string $arg) => str_starts_with($arg, '--result-file='));
        file_put_contents(substr((string) $file, strlen('--result-file=')), '-- dump');

        return Process::result();
    });

    $this->artisan('bayi:backup')->assertSuccessful();

    $zips = glob(storage_path(BackupService::DIRECTORY).DIRECTORY_SEPARATOR.'*.zip') ?: [];
    restoreStorage($previous);

    Notification::assertSentOnDemand(DatabaseBackupNotification::class, function (DatabaseBackupNotification $n, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes['mail'] === ['yedek@example.com'] && $n->attachment !== null && $n->size > 0;
    });
    expect($zips)->toBeEmpty(); // geçici zip gönderimden sonra silinir
});

test('the backup is never mailed without a password', function () {
    $previous = useTemporaryStorage();
    config(['bayiyazilimi.backup.mail_to' => 'yedek@example.com', 'bayiyazilimi.backup.mail_password' => '']);
    Notification::fake();
    Process::fake(function ($process) {
        $file = collect($process->command)->first(fn (string $arg) => str_starts_with($arg, '--result-file='));
        file_put_contents(substr((string) $file, strlen('--result-file=')), '-- dump');

        return Process::result();
    });

    $this->artisan('bayi:backup')->assertSuccessful();
    restoreStorage($previous);

    Notification::assertNothingSent();
});

test('the backup archive is AES encrypted', function () {
    $previous = useTemporaryStorage();
    file_put_contents(storage_path(BackupService::DIRECTORY.'/2000-01-01-000000.sql'), '-- dump');
    $relative = storage_path(BackupService::DIRECTORY.'/2000-01-01-000000.sql');

    $zip = app(BackupService::class)->archive($relative, 'gizli-sifre');
    $stat = null;
    if ($zip !== null) {
        $archive = new ZipArchive;
        $archive->open($zip);
        $stat = $archive->statName('2000-01-01-000000.sql');
        $archive->close();
        @unlink($zip);
    }
    restoreStorage($previous);

    expect($zip)->not->toBeNull()
        ->and($stat['encryption_method'] ?? null)->toBe(ZipArchive::EM_AES_256);
});

test('the backup mail explains how to open the encrypted attachment', function () {
    $html = (new DatabaseBackupMail('2000-01-01-000000.sql', 2 * 1048576, __FILE__))->render();

    expect($html)->toContain('2000-01-01-000000.sql')->toContain('2,0 MB')->toContain('7-Zip');
});
