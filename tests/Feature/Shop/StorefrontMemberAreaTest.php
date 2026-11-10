<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\QuoteStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function memberOrder(?string $accountId, string $number): Order
{
    return Order::create([
        'number' => $number, 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer', 'account_id' => $accountId, 'customer_name' => 'Üye',
        'email' => 'user@user.com', 'phone' => '0555', 'billing_address' => ['name' => 'Üye'],
        'shipping_address' => ['name' => 'Üye'], 'currency_code' => 'TRY', 'grand_total' => 120,
    ]);
}

it('lists only the member account orders and quotes', function () {
    $this->withoutVite();
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $other = Account::query()->create(['code' => 'OTHER0001', 'title' => 'Diğer']);

    $mine = memberOrder($member->account_id, 'S2610310001');
    $foreign = memberOrder($other->id, 'S2610310002');

    Quote::create([
        'number' => 'T2610310001', 'status' => QuoteStatus::Pending, 'account_id' => $member->account_id,
        'customer_name' => 'Üye', 'email' => 'user@user.com', 'currency_code' => 'TRY',
    ]);

    $this->actingAs($member)
        ->get(route('app.shop.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/shop/orders/index')
            ->has('orders.data', 1)
            ->where('orders.data.0.number', 'S2610310001'));

    $this->actingAs($member)
        ->get(route('app.shop.orders.show', $mine->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('app/shop/orders/show')->where('order.number', 'S2610310001'));

    $this->actingAs($member)->get(route('app.shop.orders.show', $foreign->id))->assertNotFound();

    $this->actingAs($member)
        ->get(route('app.shop.quotes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('app/shop/quotes/index')->has('quotes.data', 1));
});
