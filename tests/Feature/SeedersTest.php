<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\CoreDataSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleCatalogSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Support\Facades\Hash;

test('core data is loaded and can be re-run without creating accounts', function () {
    $users = User::query()->count();

    $this->seed(CoreDataSeeder::class);

    expect(User::query()->count())->toBe($users)
        ->and(City::query()->count())->toBe(81)
        ->and(Currency::query()->where('code', 'TRY')->exists())->toBeTrue()
        ->and(Tax::query()->where('name', 'KDV %20')->exists())->toBeTrue()
        ->and(Role::query()->whereIn('name', ['Super Admin', 'Admin'])->count())->toBe(2)
        ->and(Setting::get('app_name'))->toBe('Bayi Yazılımı');
});

test('the sample catalog adds categories, brands and stocked products once', function () {
    $this->seed(SampleCatalogSeeder::class);
    $this->seed(SampleCatalogSeeder::class);

    $products = Product::query()->where('sku', 'like', 'ORN-%')->with('category.parent')->get();
    $drill = $products->firstWhere('sku', 'ORN-2001');
    $cableDrum = $products->firstWhere('sku', 'ORN-1003');

    expect($products)->toHaveCount(25)
        ->and(Category::query()->whereNull('parent_id')->whereIn('name', ['Hırdavat', 'Tesisat'])->count())->toBe(2)
        ->and(Brand::query()->where('name', 'Delta Pro')->exists())->toBeTrue()
        ->and($drill->price)->toEqualDecimal('3250')
        ->and($drill->stock)->toBe(18)
        ->and($drill->category->parent?->name)->toBe('Hırdavat')
        // Teklifle satılan ürün stok takibi yapmaz.
        ->and($cableDrum->sale_mode)->toBe(SaleMode::Quote)
        ->and($cableDrum->track_stock)->toBeFalse();
});

test('sample accounts get a random password unless one is given', function () {
    $this->seed(SampleDataSeeder::class);

    $dealer = User::query()->where('email', 'user@user.com')->firstOrFail();

    expect(Hash::check('password', $dealer->password))->toBeFalse()
        ->and($dealer->account?->dealer_group_id)->not->toBeNull();

    (new SampleDataSeeder)->setContainer(app())->__invoke(['password' => 'gizli-sifre']);

    expect(Hash::check('gizli-sifre', $dealer->fresh()->password))->toBeTrue();
});

it('refuses to run the development seeder in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => app(DatabaseSeeder::class)->run())->toThrow(RuntimeException::class, 'canlı ortamda');
});
