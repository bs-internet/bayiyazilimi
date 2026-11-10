<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shop\CustomerType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\DealerGroup;
use App\Models\Permission;
use App\Models\User;
use App\Services\Account\AccountProvisioner;
use App\Services\Panel\Settings\Permissions\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Test (demo) sunucusu hesapları.
 *
 * 1. Yetkiler › "Rotalardan Keşfet": eksik panel yetkilerini ekler.
 * 2. Örnek hesapları bayiyazilimi-demo.com hesaplarına dönüştürür: eski hesap varsa
 *    e-posta / ad / şifresi güncellenir (siparişleri, adresleri, hesap kaydı
 *    korunur), yoksa yeni hesap açılır. Müşteri demo hesabı yalnız vitrinle
 *    (müşteri hesapları varken); İçerik Editörü rolü ve hesabı vitrin modülündedir.
 *
 * Tekrar çalıştırılabilir: php artisan db:seed --class=DemoAccountsSeeder --force
 */
class DemoAccountsSeeder extends Seeder
{
    private const PASSWORD = 'BayiDemo.123!';

    public function run(PermissionService $permissions): void
    {
        DB::transaction(function () use ($permissions): void {
            $this->discoverPermissions($permissions);

            $admin = $this->account('admin@admin.com', 'yonetici@bayiyazilimi-demo.com', 'Demo Yönetici', UserType::Admin);
            $admin->syncRoles(['Super Admin']);

            $super = $this->account('panel@admin.com', 'super@bayiyazilimi-demo.com', 'Demo Super Admin', UserType::Admin);
            $super->syncRoles(['Super Admin']);

            $dealer = $this->account('user@user.com', 'bayi@bayiyazilimi-demo.com', 'Demo Bayi', UserType::Member);
            $dealerGroup = DealerGroup::query()->firstOrCreate(['name' => 'Standart Bayi'], ['discount_rate' => 10]);
            app(AccountProvisioner::class)->ensureForUser($dealer)
                ?->update(['title' => 'Demo Bayi', 'type' => CustomerType::Dealer, 'dealer_group_id' => $dealerGroup->id]);

            // Müşteri demo hesabı yalnız müşteri hesapları varken (vitrin).
            if (CustomerType::customersEnabled()) {
                $customer = $this->account('musteri@musteri.com', 'musteri@bayiyazilimi-demo.com', 'Demo Müşteri', UserType::Member);
                app(AccountProvisioner::class)->ensureForUser($customer)
                    ?->update(['title' => 'Demo Müşteri', 'type' => CustomerType::Customer, 'dealer_group_id' => null]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Demo hesapları hazır (şifre: '.self::PASSWORD.').');
    }

    /**
     * Panel rotalarından ve modül kayıtlarından henüz eklenmemiş yetkileri ekler
     * (Yetkiler › "Rotalardan Keşfet" ile aynı liste ve önerilen grup / ad).
     */
    private function discoverPermissions(PermissionService $permissions): void
    {
        $created = 0;

        foreach ($permissions->discoverableRoutes() as $candidate) {
            Permission::query()->create([
                'name' => $candidate['name'],
                'guard_name' => 'web',
                'group' => $candidate['suggested_group'],
                'label' => $candidate['suggested_label'],
            ]);
            $created++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info("Rotalardan keşfedilen yeni yetki: {$created}");
    }

    /**
     * Eski örnek hesabı yeni adrese taşır ya da yeni hesap açar.
     */
    private function account(?string $oldEmail, string $email, string $name, UserType $type): User
    {
        $user = User::query()->where('email', $email)->first()
            ?? ($oldEmail ? User::query()->where('email', $oldEmail)->first() : null)
            ?? new User;

        $user->forceFill([
            'email' => $email,
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'status' => UserStatus::Active,
            'user_type' => $type,
            'locale' => $user->locale ?? 'tr',
            'timezone' => $user->timezone ?? 'Europe/Istanbul',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        // Yeni adres zaten varken eski örnek hesap da kaldıysa silinir.
        if ($oldEmail !== null) {
            User::query()->where('email', $oldEmail)->whereKeyNot($user->getKey())->get()->each->delete();
        }

        return $user;
    }
}
