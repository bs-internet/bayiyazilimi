<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Status;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardOrder(array $attributes = []): Order
{
    $order = Order::query()->create(array_merge([
        'number' => 'S'.random_int(100000, 999999),
        'status' => OrderStatus::Processing,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer',
        'customer_name' => 'Ayşe',
        'email' => 'ayse@example.com',
        'phone' => '5550000000',
        'billing_address' => ['name' => 'Ayşe'],
        'shipping_address' => ['name' => 'Ayşe'],
        'currency_code' => 'TRY',
        'exchange_rate' => 1,
        'grand_total' => 100,
    ], $attributes));

    $order->items()->create([
        'sku' => 'DB-1', 'name' => 'Panel Ürünü', 'quantity' => 2,
        'unit_price' => 50, 'tax_rate' => 0, 'tax_amount' => 0, 'line_total' => $order->grand_total,
    ]);

    return $order;
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();

    Currency::query()->updateOrCreate(['code' => 'EUR'], [
        'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',',
        'exchange_rate' => 40, 'status' => Status::Active, 'sort_order' => 2,
    ]);
});

test('panel dashboard renders e-commerce summary with TL revenue', function () {
    dashboardOrder(['grand_total' => 1000]);
    dashboardOrder(['grand_total' => 10, 'currency_code' => 'EUR', 'exchange_rate' => 40]); // 400 TL
    dashboardOrder(['grand_total' => 5000, 'status' => OrderStatus::Cancelled]);
    dashboardOrder([
        'grand_total' => 200,
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer',
    ]);
    $old = dashboardOrder(['grand_total' => 800]);
    $old->forceFill(['created_at' => now()->subDays(45)])->save();

    $this->actingAs($this->admin)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/dashboard')
            ->where('can.orders', true)
            ->where('kpis.revenue.value', '1600.00')
            ->where('kpis.revenue.formatted', '1.600,00 ₺')
            ->where('kpis.revenue.change', 100)
            ->where('kpis.orders.value', 3)
            ->where('kpis.average_order.formatted', '533,33 ₺')
            ->has('dailyRevenue', 30)
            ->has('ordersByStatus', count(OrderStatus::cases()))
            ->has('recentOrders', 5)
            ->has('topProducts', 1)
            ->where('topProducts.0.sku', 'DB-1')
            ->where('topProducts.0.quantity', 6)
            ->where('pending.awaiting_payment', 1)
            ->has('pending.quotes')
            ->has('pending.returns')
            ->has('pending.out_of_stock')
            ->has('newMembers.total')
            ->has('activity'));
});

test('panel dashboard hides data the user cannot access', function () {
    $user = User::factory()->create(['user_type' => UserType::Admin, 'status' => UserStatus::Active]);
    dashboardOrder();

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('panel/dashboard')
            ->where('can.orders', false)
            ->where('kpis', null)
            ->where('recentOrders', [])
            ->where('pending.quotes', null)
            ->where('activity', []));
});
