<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Http\Requests\Install\InstallDatabaseRequest;
use App\Http\Requests\Install\InstallRequest;
use App\Services\Install\Installer;
use App\Support\Install\DatabaseErrorMessage;
use App\Support\Install\Requirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Kurulum sihirbazı: gereksinimler → veritabanı → site ve yönetici.
 */
class InstallController extends Controller
{
    public function show(Request $request, Requirements $requirements, Installer $installer): Response
    {
        /** @var array<string, mixed> $mysql */
        $mysql = config('database.connections.mysql', []);

        return Inertia::render('install/index', [
            'requirements' => $requirements->check(),
            'passes' => $requirements->passes(),
            // .env'deki bilgilerle bağlanılabiliyorsa (VPS'te elle girilmiş) adım atlanır.
            'database_ready' => $request->session()->get('install.database') === true
                || (config('database.default') === 'mysql' && $installer->testDatabase($this->currentDatabase($mysql)) === null),
            'database' => [
                'host' => (string) ($mysql['host'] ?? '127.0.0.1'),
                'port' => (string) ($mysql['port'] ?? '3306'),
                'database' => (string) ($mysql['database'] ?? ''),
                'username' => (string) ($mysql['username'] ?? ''),
            ],
            'app_url' => $request->root(),
        ]);
    }

    public function database(InstallDatabaseRequest $request, Installer $installer): RedirectResponse
    {
        /** @var array{host: string, port: int|string, database: string, username: string, password: ?string} $db */
        $db = $request->validated();

        $error = $installer->testDatabase($db);

        if ($error !== null) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $error]);
        }

        try {
            $installer->saveDatabase($db);
        } catch (Throwable $e) {
            return back()->withErrors(['database' => $e->getMessage()]);
        }

        $request->session()->put('install.database', true);

        return to_route('install.show');
    }

    public function store(InstallRequest $request, Installer $installer, Requirements $requirements): Response|RedirectResponse
    {
        if (! $requirements->passes()) {
            return to_route('install.show');
        }

        // Veritabanı adımı atlanmışsa (oturum bayrağı yok ve .env bağlantısı çalışmıyor) kurulum başlamaz.
        /** @var array<string, mixed> $mysql */
        $mysql = config('database.connections.mysql', []);

        if ($request->session()->get('install.database') !== true
            && (config('database.default') !== 'mysql' || $installer->testDatabase($this->currentDatabase($mysql)) !== null)) {
            return to_route('install.show')->withErrors(['database' => __('Önce veritabanı bağlantısını kaydedin.')]);
        }

        try {
            $admin = $installer->install([
                'app_name' => (string) $request->validated('app_name'),
                'app_url' => $request->root(),
                'admin_name' => (string) $request->validated('admin_name'),
                'admin_email' => (string) $request->validated('admin_email'),
                'admin_password' => (string) $request->validated('admin_password'),
                'sample_data' => $request->boolean('sample_data'),
            ]);
        } catch (Throwable $e) {
            report($e);

            // Başlık ("Kurulum tamamlanamadı") sayfada; burada yalnız anlaşılır neden.
            return back()->withInput($request->except('admin_password', 'admin_password_confirmation'))->withErrors([
                'install' => DatabaseErrorMessage::for($e, (string) config('database.connections.mysql.database')),
            ]);
        }

        return Inertia::render('install/done', [
            'admin_email' => $admin->email,
            'login_url' => route('login'),
            'sample_data' => $request->boolean('sample_data'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $mysql
     * @return array{host: string, port: int|string, database: string, username: string, password: ?string}
     */
    private function currentDatabase(array $mysql): array
    {
        return [
            'host' => (string) ($mysql['host'] ?? ''),
            'port' => (string) ($mysql['port'] ?? '3306'),
            'database' => (string) ($mysql['database'] ?? ''),
            'username' => (string) ($mysql['username'] ?? ''),
            'password' => isset($mysql['password']) ? (string) $mysql['password'] : null,
        ];
    }
}
