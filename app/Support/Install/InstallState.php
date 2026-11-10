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
 * ile kurulan geliştirme ortamı) veritabanında kullanıcı varsa kurulu sayılır
 * ve değer `.env`'e yazılır — bu kontrol yalnız değer yokken yapılır.
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

        if ($this->hasExistingData()) {
            $this->markInstalled();

            return true;
        }

        return $this->installed = false;
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
     * Değer yokken kurulu sayılma koşulu: bir Super Admin var. Yalnız "kullanıcı var"
     * yetmez: yarıda kalan kurulum (örnek veriler yüklenmiş, yönetici henüz
     * oluşmamış) kurulu sayılır ve sihirbaz kapanırdı — giriş yapacak kimse olmazdı.
     */
    private function hasExistingData(): bool
    {
        if ((string) config('app.key') === '') {
            return false;
        }

        return $this->hasSuperAdmin();
    }

    public function hasSuperAdmin(): bool
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
            return false;
        }
    }
}
