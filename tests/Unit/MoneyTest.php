<?php

declare(strict_types=1);

use App\Support\Money;

/*
 * Kesin para hesabı köprüsü (brick/money). Para birimi açıkça verilir;
 * ana birim (veritabanı) gerektirmez.
 */

it('adds without float drift', function () {
    $total = Money::zero('TRY');

    foreach (range(1, 1000) as $ignored) {
        $total = $total->plus(Money::of(0.1, 'TRY'));
    }

    expect((string) $total->getAmount())->toBe('100.00')
        ->and((string) Money::of(0.1, 'TRY')->plus(Money::of(0.2, 'TRY'))->getAmount())->toBe('0.30');
});

it('rounds half up to two decimals from strings, floats and integers', function () {
    expect((string) Money::of('10.005', 'TRY')->getAmount())->toBe('10.01')
        ->and((string) Money::of(-12.345, 'TRY')->getAmount())->toBe('-12.35')
        ->and((string) Money::of(7, 'TRY')->getAmount())->toBe('7.00')
        ->and((string) Money::of(null, 'TRY')->getAmount())->toBe('0.00')
        ->and((string) Money::of(1.0E-7, 'TRY')->getAmount())->toBe('0.00');
});

it('converts with document rates exactly', function () {
    expect(Money::convertWithRates(100, '36.1234', 1))->toBeMoney('3612.34')
        ->and(Money::convertWithRates('99.99', 1, '34.5'))->toBeMoney('2.90')
        ->and(Money::convertWithRates(10, 0, 0))->toBeMoney('10.00');
});

it('computes document lines, rates and allocations exactly', function () {
    expect(Money::line('80.5000', 3, 20))->toBeMoney(['net' => '241.50', 'tax' => '48.30', 'gross' => '289.80'])
        ->and(Money::line('0.3333', 3, 18, '0.10'))->toBeMoney(['net' => '0.90', 'tax' => '0.16', 'gross' => '1.06'])
        ->and(Money::withRate('99.99', 20))->toBeMoney('119.99')
        ->and(Money::percent(1200, 3))->toBeMoney('36.00')
        ->and(Money::times('80.5', 3))->toBeMoney('241.50')
        ->and(Money::add(0.1, '0.2', 0.3))->toBeMoney('0.60')
        ->and(Money::allocate(10, ['a' => 1, 'b' => 1, 'c' => 1]))->toBeMoney(['a' => '3.34', 'b' => '3.33', 'c' => '3.33'])
        ->and(Money::add(...Money::allocate(100, ['x' => 33.33, 'y' => 33.33, 'z' => 33.34])))->toBeMoney('100.00');
});

it('sums mixed inputs and works with custom (non-ISO) codes', function () {
    expect((string) Money::sum([0.1, '0.2', Money::of(1, 'XAU')], 'XAU')->getAmount())->toBe('1.30')
        ->and(Money::toFloat(Money::of('33.33', 'TRY')->plus(Money::of('33.33', 'TRY'))->plus(Money::of('33.34', 'TRY'))))->toBe(100.0);
});
