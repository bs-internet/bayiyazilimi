<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\PaymentNotificationStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Events\Shop\OrderPaidEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Services\Shop\PaymentNotificationService;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/*
 * Eşzamanlı istek korumaları. SQLite tek bağlantıda gerçek yarış kurulamadığı
 * için ikinci istek "bayat" (işlem öncesi okunmuş) bir modelle taklit edilir:
 * servis, transaction içinde kilitli yeniden okumayla güncel durumu görmelidir.
 */
beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Carrier::query()->update(['status' => Status::Passive->value]);
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);

    $this->admin = User::factory()->admin()->create();
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->account = $this->member->account;
    $this->account->update(['type' => CustomerType::Dealer, 'dealer_group_id' => null, 'discount_rate' => 0]);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'EZ-1', 'name' => 'Eşzamanlı Ürün', 'slug' => 'eszamanli-urun', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 50, StockMovementType::In);

    $this->place = function (int $qty): Order {
        $this->actingAs($this->member);
        $cart = Basket::cart();
        $cart->add($this->product->fresh(), $qty);

        return app(OrderService::class)->placeFromCart($cart, [
            'email' => 'bayi@example.com', 'phone' => '05550000000',
            'billing' => ['type' => 'corporate', 'name' => 'Bayi', 'address' => 'Adres', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'same_address' => true, 'payment_method' => 'bank_transfer',
        ], $this->member->fresh(), $this->try);
    };
});

it('approves a payment notification only once when two admins approve at the same time', function () {
    $order = ($this->place)(2); // 240 TL
    $service = app(PaymentNotificationService::class);
    $notification = $service->submit($this->account, $this->member, ['order_id' => $order->id, 'amount' => '240', 'transfer_date' => now()->toDateString()], null);
    $stale = PaymentNotification::query()->withoutGlobalScopes()->findOrFail($notification->id);

    $service->approve($notification, $this->admin);

    expect(fn () => $service->approve($stale, $this->admin))->toThrow(ShopException::class)
        ->and(fn () => $service->reject($stale, $this->admin, 'Geç kaldı'))->toThrow(ShopException::class);

    expect(Payment::query()->withoutGlobalScopes()->where('provider', 'notification')->count())->toBe(1)
        ->and($notification->fresh()->status)->toBe(PaymentNotificationStatus::Approved)
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('0.0');
});

it('refuses a payment notification for another account\'s order', function () {
    $order = ($this->place)(1);

    expect(fn () => app(PaymentNotificationService::class)->submit(Account::factory()->create(), $this->member, [
        'order_id' => $order->id, 'amount' => '120', 'transfer_date' => now()->toDateString(),
    ], null))->toThrow(ShopException::class, 'Seçilen sipariş bulunamadı.');

    expect(PaymentNotification::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('credits a short approved amount to the ledger without settling the order', function () {
    $order = ($this->place)(2); // 240 TL
    $service = app(PaymentNotificationService::class);
    $notification = $service->submit($this->account, $this->member, ['order_id' => $order->id, 'amount' => '240', 'transfer_date' => now()->toDateString()], null);

    // Yönetici hesaba geçen tutarı 100 TL olarak düzeltir: sipariş havale beklemeye devam eder.
    $payment = $service->approve($notification, $this->admin, 100);

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount)->toEqualDecimal('100.0')
        ->and($notification->fresh()->amount)->toEqualDecimal('100.0')
        ->and($order->fresh()->payment_status)->not->toBe(PaymentStatus::Paid);
});

it('marks an order paid once when a stale copy is marked paid again', function () {
    Event::fake([OrderPaidEvent::class]);
    $order = ($this->place)(2);
    $stale = Order::query()->withoutGlobalScopes()->findOrFail($order->id);
    $staleTimeout = Order::query()->withoutGlobalScopes()->findOrFail($order->id); // zaman aşımı komutunun gördüğü hali

    app(OrderService::class)->markPaid($order, $this->admin);
    app(OrderService::class)->markPaid($stale, $this->admin);
    app(OrderService::class)->markPaymentFailed($staleTimeout, 'Zaman aşımı');

    Event::assertDispatchedTimes(OrderPaidEvent::class, 1);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payments()->where('status', PaymentStatus::Paid)->count())->toBe(1)
        ->and($this->product->fresh()->stock)->toBe(48)
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('0.0');
});

it('hands out sequential document numbers from a locked counter', function () {
    $stamp = now()->format('ymd');

    // Gün içinde sayaç yokken verilmiş numaralardan devam eder.
    $first = ($this->place)(1);
    DB::table('document_sequences')->delete();
    $second = OrderService::nextNumber('S', 'orders');
    $third = OrderService::nextNumber('S', 'orders');

    expect($first->number)->toBe("S{$stamp}0001")
        ->and($second)->toBe("S{$stamp}0002")
        ->and($third)->toBe("S{$stamp}0003")
        ->and(DB::table('document_sequences')->where('key', "orders:S{$stamp}")->value('value'))->toBe(3);
});
