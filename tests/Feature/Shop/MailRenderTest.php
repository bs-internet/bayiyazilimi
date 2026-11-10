<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Mail\Shop\NewOrderAdminMail;
use App\Mail\Shop\OrderReceivedMail;
use App\Mail\Shop\OrderStatusMail;
use App\Models\Order;
use App\Models\User;

// Sipariş e-postaları gerçekten derlenip işlenir (çeviri yer tutucuları, tutar biçimi).
it('renders the order mails with translated text and exact amounts', function () {
    $member = User::query()->where('email', 'user@user.com')->firstOrFail();

    $order = Order::query()->create([
        'number' => 'S123456', 'status' => OrderStatus::Shipped, 'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer', 'user_id' => $member->id, 'account_id' => $member->account_id,
        'customer_name' => 'Ali Veli', 'email' => 'ali@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali Veli'], 'shipping_address' => ['name' => 'Ali Veli'],
        'currency_code' => 'TRY', 'subtotal' => '200.00', 'tax_total' => '40.00', 'shipping_total' => '0',
        'grand_total' => '240.00', 'cargo_company' => 'Kargo A', 'tracking_number' => 'TRK1',
    ]);
    $order->items()->create([
        'sku' => 'A-1', 'name' => 'Ürün A', 'quantity' => 2, 'unit_price' => '100.0000',
        'tax_rate' => '20.00', 'tax_amount' => '40.00', 'line_total' => '240.00',
    ]);

    $received = (new OrderReceivedMail($order))->render();
    $status = (new OrderStatusMail($order, 'Kargoya verildi.'))->render();
    $admin = (new NewOrderAdminMail($order))->render();

    expect($received)->toContain('S123456')->toContain('Ücretsiz')->toContain('240,00')
        ->and($status)->toContain('Kargoya Verildi')->toContain('TRK1')
        ->and($admin)->toContain('Ali Veli')
        ->and((new OrderStatusMail($order))->envelope()->subject)->toContain('S123456');
});
