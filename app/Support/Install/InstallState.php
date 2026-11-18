<?php

declare(strict_types=1);

namespace App\Support\Install;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sistem kurulmuş mu? Kurulum bitince `.env`'e `BAYIYAZILIMI_INSTALLED=true`
 * yazılır (`bayiyazilimi.installed`); kurulu sistemde kurulum rotaları hiç
 * yüklenmez.
 *
 * Değer `.env`'de yoksa (sihirbazdan önce kurulmuş sistem, `migrate --seed`
 * ile kurulan geliştirme ortamı) veritabanında Super Admin varsa kurulu sayılır
 * ve değer `.env`'e yazılır — bu kontrol yalnız değer yokken yapılır (bkz. detect()).
 */
final class InstallState
{
    public const ENV_KEY = 'BAYIYAZILIMI_INSTALLED';

    private ?bool $installed = null;

    public function __construct(private readonly EnvironmentFile $env) {}

    public function installed(): bool
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $value = config('bayiyazilimi.installed');

        if (is_bool($value)) {
            return $this->installed = $value;
        }

        return $this->installed = $this->detect();
    }

    /**
     * `.env` bayrağı yokken karar. Kurulumu kilitleyen asıl bilgi bayraktır; bu
     * yol yalnız bayrak yazılmadan önce kurulmuş ya da `.env`'i elle değiştirilmiş
     * sistem içindir.
     *
     * - Super Admin varsa kurulu: bayrak yazılır.
     * - Veritabanına ulaşılamıyorsa canlı ortam (APP_ENV=production) kurulu
     *   sayılır: geçici bir veritabanı kesintisi sihirbazı herkese açıp `.env`'e
     *   başka bir veritabanı yazdırmaya izin vermemeli. Sihirbaz yalnız henüz
     *   canlıya alınmamış (APP_ENV ≠ production) sistemde açılır; kurulum bitince
     *   APP_ENV=production yazılır.
     */
    private function detect(): bool
    {
        if ((string) config('app.key') === '') {
            return false;
        }

        $superAdmin = $this->superAdminState();

        if ($superAdmin === true) {
            $this->markInstalled();

            return true;
        }

        if ($superAdmin === null) {
            return app()->isProduction();
        }

        return false;
    }

    public function markInstalled(): void
    {
        try {
            $this->env->set([self::ENV_KEY => true]);
        } catch (Throwable) {
            // .env yazılamıyorsa bu istek için kurulu sayılır; sonraki istekte yeniden algılanır.
        }

        config(['bayiyazilimi.installed' => true]);
        $this->installed = true;
    }

    /**
     * Super Admin var mı: true / false; veritabanına ulaşılamıyorsa null (bilinmiyor).
     * Yalnız "kullanıcı var" yetmez: yarıda kalan kurulum (örnek veriler yüklenmiş,
     * yönetici henüz oluşmamış) kurulu sayılır ve sihirbaz kapanırdı.
     */
    private function superAdminState(): ?bool
    {
        try {
            $roles = (string) config('permission.table_names.roles', 'roles');
            $pivot = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

            if (! Schema::hasTable($roles) || ! Schema::hasTable($pivot)) {
                return false;
            }

            $roleKey = (new Role)->getKeyName();
            $superAdmin = DB::table($roles)->where('name', 'Super Admin')->value($roleKey);

            return $superAdmin !== null
                && DB::table($pivot)->where(config('permission.column_names.role_pivot_key') ?? 'role_id', $superAdmin)->exists();
        } catch (Throwable) {
            return null;
        }
    }
}
