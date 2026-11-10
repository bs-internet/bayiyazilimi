<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RateSource;
use App\Models\AccountTransaction;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Panel\Shop\DashboardService;
use App\Services\Shop\CurrencyManager;
use App\Services\Shop\OrderService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();

    $this->usd = Currency::query()->where('code', 'USD')->first();
    $this->usd->update(['exchange_rate' => 40, 'rate_source' => RateSource::Manual, 'status' => 'active']);
    $this->eur = Currency::query()->where('code', 'EUR')->first();
    $this->eur->update(['exchange_rate' => 50, 'rate_source' => RateSource::Manual, 'status' => 'active']);

    $this->useBase = function (Currency $currency): void {
        Setting::query()->updateOrCreate(['key' => 'default_currency_id'], ['value' => $currency->id, 'group' => 'defaults']);
        Setting::flushCache();
        app()->forgetScopedInstances();
    };
});

it('converts amounts to the base currency with cross rates', function () {
    ($this->useBase)($this->usd);

    expect(Money::baseCode())->toBe('USD')
        ->and(Money::baseRate())->toEqualDecimal('40.0')
        ->and(Money::toBase(100, $this->eur->fresh()))->toEqualDecimal('125.0')
        ->and(Money::toBase(4000, Currency::query()->where('code', 'TRY')->first()))->toEqualDecimal('100.0')
        ->and(Money::format(10))->toContain('10');
});

it('writes the ledger and the reports in the base currency using the order rates', function () {
    ($this->useBase)($this->usd);

    $order = Order::query()->create([
        'number' => 'S900001', 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending, 'payment_method' => 'bank_transfer',
        'user_id' => $this->member->id, 'account_id' => $this->member->account_id,
        'customer_name' => 'Bayi', 'email' => 'b@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Bayi'], 'shipping_address' => ['name' => 'Bayi'],
        'currency_code' => 'EUR', 'exchange_rate' => 50, 'base_exchange_rate' => 40, 'grand_total' => 80,
    ]);

    app(OrderService::class)->changeStatus($order, OrderStatus::Processing, [], $this->admin);

    $debit = AccountTransaction::query()->where('source_id', $order->id)->sole();
    expect($debit->currency_code)->toBe('USD')
        ->and($debit->amount)->toEqualDecimal('100.0');

    // Rapor / başlangıç SQL'i de aynı çevrimi yapar.
    $sum = DB::table('orders')->where('id', $order->id)->selectRaw('sum('.Money::sqlToBase('grand_total').') as t')->value('t');
    expect(Money::round($sum))->toEqualDecimal('100.0');

    $kpis = app(DashboardService::class)->orderKpis();
    expect($kpis['revenue']['formatted'])->toContain('100');
});

it('locks the base currency after the first order', function () {
    $try = Currency::query()->where('code', 'TRY')->first();
    ($this->useBase)($try);

    $this->actingAs($this->admin)
        ->post(route('panel.settings.general.update'), ['default_currency_id' => $this->usd->id])
        ->assertSessionHasNoErrors();
    expect(app(CurrencyManager::class)->base()->code)->toBe('USD');

    ($this->useBase)($try);
    Order::query()->create([
        'number' => 'S900002', 'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending, 'payment_method' => 'bank_transfer',
        'customer_name' => 'Misafir', 'email' => 'm@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'M'], 'shipping_address' => ['name' => 'M'], 'currency_code' => 'TRY', 'grand_total' => 10,
    ]);

    $this->actingAs($this->admin)
        ->post(route('panel.settings.general.update'), ['default_currency_id' => $this->usd->id])
        ->assertSessionHasErrors('default_currency_id');
    expect(Money::baseLocked())->toBeTrue();
});
