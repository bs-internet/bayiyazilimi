<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Geliştirme ve test veritabanı: temel veriler + geliştirme yöneticileri +
 * örnek veriler. Tüm hesapların şifresi "password".
 *
 * GERÇEK KURULUMDA KULLANILMAZ — kurulum temel verileri (CoreDataSeeder) ve
 * isteğe bağlı olarak örnek verileri (SampleDataSeeder) yükler, yöneticiyi
 * kullanıcının girdiği bilgilerle oluşturur.
 */
class DatabaseSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        // Şifresi bilinen Super Admin oluşturur: canlı ortamda çalışmaz.
        throw_if(app()->isProduction(), RuntimeException::class, __('DatabaseSeeder canlı ortamda çalıştırılamaz.'));

        $this->call(CoreDataSeeder::class);

        // admin@admin.com → Super Admin, panel@admin.com → Admin.
        $this->admin('admin@admin.com', 'Admin User')->syncRoles(['Super Admin']);
        $this->admin('panel@admin.com', 'Panel Admin')->syncRoles(['Admin']);

        $this->call(SampleDataSeeder::class, parameters: ['password' => self::PASSWORD]);
    }

    private function admin(string $email, string $name): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'status' => UserStatus::Active,
                'user_type' => UserType::Admin,
                'locale' => 'tr',
                'timezone' => 'Europe/Istanbul',
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
