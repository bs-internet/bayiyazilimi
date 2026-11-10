<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shop\CustomerType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\City;
use App\Models\Country;
use App\Models\DealerGroup;
use App\Models\District;
use App\Models\User;
use App\Services\Account\AccountProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Örnek veriler: kurulumda "örnek verileri yükle" seçilirse yüklenir.
 *
 * Örnek bayi grubu, örnek bayi (user@user.com) ve örnek müşteri
 * (musteri@musteri.com) hesapları, bayinin adresi ve — test dışında — örnek
 * katalog (SampleCatalogSeeder) ile örnek sipariş / iade (MemberDemoSeeder). Temel verilerin (CoreDataSeeder) yüklenmiş
 * olmasını bekler. Yönetici hesabı oluşturmaz.
 *
 * Şifre verilmezse örnek hesaplar rastgele şifreyle açılır (gerçek sunucuda
 * bilinen şifreli hesap kalmasın); geliştirmede DatabaseSeeder "password" verir.
 */
class SampleDataSeeder extends Seeder
{
    public function run(?string $password = null): void
    {
        $hash = Hash::make($password ?? Str::password(32));

        $country = Country::query()->where('code', 'TR')->firstOrFail();
        $city = City::query()->whereBelongsTo($country)->where('code', '34')->firstOrFail();
        $district = District::query()->whereBelongsTo($city)->where('name', 'Kadıköy')->firstOrFail();

        // Örnek bayi (/app).
        $dealer = $this->member('user@user.com', 'Member User', $hash);
        $account = app(AccountProvisioner::class)->ensureForUser($dealer);
        $dealerGroup = DealerGroup::query()->firstOrCreate(['name' => 'Standart Bayi'], ['discount_rate' => 10]);
        $account?->update(['title' => $dealer->name, 'type' => CustomerType::Dealer, 'dealer_group_id' => $dealerGroup->id]);
        $account?->address()->updateOrCreate([], [
            'address' => 'Örnek adres',
            'postal_code' => '34000',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
        ]);

        // Örnek müşteri (vitrin "Hesabım"): yalnız müşteri hesapları varken (vitrin).
        // Çekirdekte müşteri hesabı giriş yapamaz; ölü bir örnek hesap açılmaz.
        if (CustomerType::customersEnabled()) {
            $customer = $this->member('musteri@musteri.com', 'Örnek Müşteri', $hash);
            app(AccountProvisioner::class)->ensureForUser($customer)?->update(['title' => $customer->name, 'type' => CustomerType::Customer]);
        }

        // Örnek katalog ile örnek bayinin adresi, siparişi ve iade talebi. Testlerde
        // atlanır: her test aynı DatabaseSeeder'la başlar, ürün sayıları sabit kalsın.
        if (! app()->runningUnitTests()) {
            $this->call(SampleCatalogSeeder::class);
            $this->call(MemberDemoSeeder::class);
        }
    }

    private function member(string $email, string $name, string $hash): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $hash,
                'status' => UserStatus::Active,
                'user_type' => UserType::Member,
                'locale' => 'tr',
                'timezone' => 'Europe/Istanbul',
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
