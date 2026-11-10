<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Models\User;
use App\Services\Account\AccountProvisioner;
use App\Support\Money;
use Brick\Math\BigNumber;
use Brick\Money\AbstractMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// Kurulu modüllerin (vendor/bsinternet/*) testleri de aynı TestCase'e bağlanır;
// modül yoksa yalnız çekirdek testleri çalışır.
$moduleTests = array_map(
    fn (string $dir): string => '../vendor/bsinternet/'.basename(dirname($dir)).'/tests',
    glob(__DIR__.'/../vendor/bsinternet/*/tests', GLOB_ONLYDIR) ?: [],
);

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', ...$moduleTests);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
 * Kesin tutar karşılaştırması: BigDecimal / Money / sayısal metin (diziler dahil)
 * ölçeğiyle birlikte metne çevrilip beklenenle birebir karşılaştırılır.
 * Örn. expect($order->grand_total)->toBeMoney('510.00').
 */
expect()->extend('toBeMoney', function (string|array $expected) {
    $normalize = function (mixed $value) use (&$normalize): mixed {
        return match (true) {
            is_array($value) => array_map($normalize, $value),
            $value instanceof AbstractMoney => (string) $value->getAmount(),
            $value instanceof BigNumber => (string) $value,
            default => $value,
        };
    };

    return expect($normalize($this->value))->toBe($expected);
});

/*
 * Ölçekten bağımsız kesin eşitlik: '7.5' ile 7.50 / 7.5000 eşittir (float'a çevrilmez).
 * Örn. expect($product->price)->toEqualDecimal('1234.5').
 */
expect()->extend('toEqualDecimal', function (BigNumber|int|string $expected) {
    $actual = Money::decimal($this->value);

    expect($actual->isEqualTo(Money::decimal($expected)))
        ->toBeTrue("{$actual} ≠ {$expected}");

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Müşteri (B2C) hesaplı örnek üye. Çekirdeğin örnek verileri müşteri açmaz
 * (müşteri hesabını vitrin getirir); müşteriye özgü davranışı sınayan testler
 * kendisi kurar.
 */
function sampleCustomer(string $email = 'musteri@musteri.com'): User
{
    $user = User::query()->where('email', $email)->first()
        ?? User::factory()->create(['email' => $email, 'name' => 'Örnek Müşteri']);

    app(AccountProvisioner::class)->ensureForUser($user)
        ?->update(['title' => $user->name, 'type' => CustomerType::Customer, 'dealer_group_id' => null]);

    return $user->fresh();
}
