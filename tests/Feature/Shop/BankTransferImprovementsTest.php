<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentNotificationStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\DealerGroup;
use App\Models\Order;
use App\Models\PaymentNotification;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Shop\NewPaymentNotificationAdminNotification;
use App\Notifications\Shop\PaymentNotificationReviewedNotification;
use App\Services\Shop\Basket;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Storage::fake('private', ['serve' => true]);
    $this->bank = BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    Carrier::query()->update(['status' => Status::Passive->value]);
    ShopSettings::save(['order_notification_email' => 'siparis@example.com']);

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->account = $this->member->account;
    $this->account->update(['type' => CustomerType::Dealer, 'dealer_group_id' => null, 'discount_rate' => 0]);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'HV-1', 'name' => 'Havale Ürünü', 'slug' => 'havale-urunu', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 50, StockMovementType::In);

    $this->place = function (int $qty, string $method = 'bank_transfer'): Order {
        $this->actingAs($this->member);
        $cart = Basket::cart();
        $cart->add($this->product->fresh(), $qty);

        return app(OrderService::class)->placeFromCart($cart, [
            'email' => 'bayi@example.com', 'phone' => '05550000000',
            'billing' => ['type' => 'corporate', 'name' => 'Bayi', 'address' => 'Adres', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'same_address' => true, 'payment_method' => $method,
        ], $this->member->fresh(), $this->try);
    };
});

it('applies the bank transfer discount to the order total', function () {
    $this->actingAs($this->admin)->put(route('panel.shop.bank-accounts.discount'), ['discount_rate' => '3'])->assertSessionHasNoErrors();

    $methods = CheckoutController::paymentMethods($this->member->fresh(), 1200, $this->try);
    expect($methods[0]['adjustment_rate'])->toEqualDecimal('-3.0')
        ->and($methods[0]['adjustment_label'])->toBe('Havale indirimi (%3)');

    $order = ($this->place)(10); // 1200 TL

    expect($order->payment_adjustment)->toEqualDecimal('-36.0')
        ->and($order->payment_adjustment_label)->toBe('Havale indirimi (%3)')
        ->and($order->grand_total)->toEqualDecimal('1164.0');
});

it('lets a dealer notify a transfer with a receipt and the admin approves it to settle the order', function () {
    $order = ($this->place)(2); // 240 TL, havale bekliyor

    $this->actingAs($this->member)->get(route('app.shop.payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('notify.banks', 1)->has('notify.orders', 1));

    $this->actingAs($this->member)->post(route('app.shop.payment-notifications.store'), [
        'amount' => '240', 'transfer_date' => now()->toDateString(), 'bank_account_id' => $this->bank->id,
        'order_id' => $order->id, 'sender_name' => 'Bayi Ltd', 'receipt' => UploadedFile::fake()->image('dekont.jpg'),
    ])->assertSessionHasNoErrors();

    $notification = PaymentNotification::query()->sole();
    expect($notification->status)->toBe(PaymentNotificationStatus::Pending)
        ->and($notification->firstMediaIn(PaymentNotification::RECEIPT))->not->toBeNull();
    Notification::assertSentOnDemand(NewPaymentNotificationAdminNotification::class);

    $this->flushSession();
    $this->actingAs($this->admin)->get(route('panel.shop.payment-notifications.index'))
        ->assertInertia(fn (Assert $page) => $page->component('panel/shop/ledger/payment-notifications')->where('notifications.data.0.has_receipt', true));
    $this->actingAs($this->admin)->get(route('panel.shop.payment-notifications.receipt', $notification->id))->assertRedirect();

    $this->actingAs($this->admin)->post(route('panel.shop.payment-notifications.approve', $notification->id), ['amount' => '240'])->assertSessionHasNoErrors();

    expect($notification->fresh()->status)->toBe(PaymentNotificationStatus::Approved)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($order->payments()->sole()->provider)->toBe('notification')
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('0.0');
    Notification::assertSentTo($this->member, PaymentNotificationReviewedNotification::class);
});

it('credits an approved balance payment and rejects with a reason', function () {
    $group = DealerGroup::query()->create(['name' => 'Vadeli', 'discount_rate' => 0, 'payment_terms' => 'credit', 'payment_term_days' => 30, 'over_limit_action' => 'approval', 'overdue_action' => 'approval']);
    $this->account->update(['dealer_group_id' => $group->id]);
    $order = ($this->place)(5, 'on_account'); // 600 TL vadeli

    $this->actingAs($this->member)->post(route('app.shop.payment-notifications.store'), [
        'amount' => '200', 'transfer_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->member)->post(route('app.shop.payment-notifications.store'), [
        'amount' => '999', 'transfer_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $first = PaymentNotification::query()->where('amount', 200)->sole();
    $second = PaymentNotification::query()->where('amount', 999)->sole();
    $this->flushSession();

    $this->actingAs($this->admin)->post(route('panel.shop.payment-notifications.approve', $first->id), [])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('panel.shop.payment-notifications.reject', $second->id), ['reason' => 'Hesaba ulaşmadı'])->assertSessionHasNoErrors();

    expect(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('400.0')
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Partial)
        ->and($second->fresh()->status)->toBe(PaymentNotificationStatus::Rejected)
        ->and($second->fresh()->reject_reason)->toBe('Hesaba ulaşmadı');

    // Sonuçlanmış bildirim yeniden onaylanamaz.
    $this->actingAs($this->admin)->post(route('panel.shop.payment-notifications.approve', $second->id), []);
    expect($second->fresh()->status)->toBe(PaymentNotificationStatus::Rejected);
});
