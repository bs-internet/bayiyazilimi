<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Events\Shop\OrderInvoicedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Services\Shop\OrderService;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function invoiceTestOrder(string $number, OrderStatus $status): Order
{
    return Order::query()->create([
        'number' => $number, 'status' => $status, 'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'bank_transfer', 'customer_name' => 'Ali', 'email' => 'ali@example.com', 'phone' => '0555',
        'billing_address' => ['name' => 'Ali'], 'shipping_address' => ['name' => 'Ali'], 'currency_code' => 'TRY',
        'subtotal' => 100, 'tax_total' => 20, 'grand_total' => 120,
    ]);
}

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

test('completed orders can be marked invoiced, edited and cleared', function () {
    $order = invoiceTestOrder('S-INV-1', OrderStatus::Completed);

    $this->actingAs($this->admin)->put(route('panel.shop.orders.invoice', $order->id), ['invoice_number' => ' GIB2026000001 '])
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->invoice_number)->toBe('GIB2026000001')
        ->and($order->invoiced_at)->not->toBeNull()
        ->and(OrderHistory::query()->where('order_id', $order->id)->where('note', 'like', '%GIB2026000001%')->exists())->toBeTrue();

    $this->actingAs($this->admin)->get(route('panel.shop.orders.show', $order->id))
        ->assertInertia(fn (Assert $p) => $p->where('order.invoice_number', 'GIB2026000001'));

    $this->actingAs($this->admin)->put(route('panel.shop.orders.invoice', $order->id), ['invoice_number' => ''])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->invoice_number)->toBeNull()
        ->and($order->fresh()->invoiced_at)->toBeNull();
});

test('orders that are not completed cannot be invoiced', function () {
    $order = invoiceTestOrder('S-INV-2', OrderStatus::Processing);

    $this->actingAs($this->admin)->put(route('panel.shop.orders.invoice', $order->id), ['invoice_number' => 'X1'])
        ->assertSessionHasErrors('invoice_number');

    expect($order->fresh()->invoice_number)->toBeNull();
});

test('order list filters invoiced and completed-but-not-invoiced orders', function () {
    $invoiced = invoiceTestOrder('S-INV-3', OrderStatus::Completed);
    $invoiced->forceFill(['invoice_number' => 'F-3', 'invoiced_at' => now()])->save();
    invoiceTestOrder('S-INV-4', OrderStatus::Completed);
    invoiceTestOrder('S-INV-5', OrderStatus::Pending);

    $this->actingAs($this->admin)->get(route('panel.shop.orders.index', ['invoice' => 'kesildi']))
        ->assertInertia(fn (Assert $p) => $p->where('orders.data', fn ($rows) => collect($rows)->pluck('number')->all() === ['S-INV-3']));

    $this->actingAs($this->admin)->get(route('panel.shop.orders.index', ['invoice' => 'kesilmedi']))
        ->assertInertia(fn (Assert $p) => $p->where('orders.data', fn ($rows) => collect($rows)->pluck('number')->all() === ['S-INV-4']));
});

test('extensions record invoices through the service and add order screen data', function () {
    Event::fake([OrderInvoicedEvent::class]);
    $order = invoiceTestOrder('S-INV-9', OrderStatus::Completed);

    app(OrderService::class)->recordInvoice($order, 'PRS2026000009');

    expect($order->fresh()->invoice_number)->toBe('PRS2026000009');
    Event::assertDispatched(OrderInvoicedEvent::class, fn ($e) => $e->invoiceNumber === 'PRS2026000009' && $e->causer === null);

    // Tamamlanmamış siparişe fatura yazılamaz.
    expect(fn () => app(OrderService::class)->recordInvoice(invoiceTestOrder('S-INV-10', OrderStatus::Pending), 'X'))
        ->toThrow(ShopException::class);

    hooks()->filter('panel.orders.show.extensions', fn (array $value, Order $order) => [...$value, 'muhasebe' => ['sent' => $order->number]]);

    $this->actingAs($this->admin)->get(route('panel.shop.orders.show', $order->id))
        ->assertInertia(fn (Assert $p) => $p->where('extensions.muhasebe.sent', 'S-INV-9'));
});
