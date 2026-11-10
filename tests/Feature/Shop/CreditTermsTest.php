<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Http\Controllers\Shop\CheckoutController;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\DealerGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\CollectionService;
use App\Services\Shop\CreditService;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);
    Carrier::query()->update(['status' => Status::Passive->value]);

    $this->admin = User::factory()->admin()->create();
    $this->admin->assignRole('Super Admin');
    $this->member = User::query()->where('email', 'user@user.com')->firstOrFail();
    $this->group = DealerGroup::query()->create([
        'name' => 'Vadeli Bayiler', 'discount_rate' => 0,
        'payment_terms' => 'credit', 'credit_limit' => 1000, 'payment_term_days' => 30,
        'over_limit_action' => 'approval', 'overdue_action' => 'approval',
    ]);
    $this->account = $this->member->account;
    $this->account->update(['type' => CustomerType::Dealer, 'dealer_group_id' => $this->group->id, 'discount_rate' => 0]);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->product = Product::create([
        'sku' => 'VD-1', 'name' => 'Vadeli Ürün', 'slug' => 'vadeli-urun', 'price' => 100,
        'currency_id' => $this->try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 100, StockMovementType::In);

    $this->place = function (int $qty, string $method = 'on_account'): Order {
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

it('offers the on-account method only to credit dealers and respects allowed methods', function () {
    $methods = fn () => array_column(CheckoutController::paymentMethods($this->member->fresh(), 100, $this->try), 'value');

    expect($methods())->toBe(['bank_transfer', 'on_account']);

    $this->group->update(['payment_terms' => 'prepaid']);
    expect($methods())->toBe(['bank_transfer']);

    // Bayiye özel: vadeli + yalnız cari hesaba.
    $this->account->update(['payment_terms' => 'credit', 'allowed_payment_methods' => ['on_account']]);
    expect($methods())->toBe(['on_account']);
});

it('approves an on-account order within the limit and writes the debit with the due date', function () {
    $order = ($this->place)(5); // 600 TL

    expect($order->status)->toBe(OrderStatus::Processing)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->due_date?->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal($order->grand_total)
        ->and(app(CreditService::class)->available($this->account->fresh()))->toEqualDecimal(Money::sub(1000, $order->grand_total));
});

it('sends an order over the limit to approval and the admin approves it', function () {
    ($this->place)(5); // 600 TL, onaylanır
    $second = ($this->place)(5); // risk 1200 > 1000

    expect($second->status)->toBe(OrderStatus::AwaitingApproval)
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('600.0')
        ->and(app(CreditService::class)->risk($this->account->fresh()))->toEqualDecimal('1200.0');

    app(OrderService::class)->changeStatus($second, OrderStatus::Processing, ['note' => 'Onaylandı'], $this->admin);

    expect($second->fresh()->due_date?->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('1200.0');
});

it('blocks an on-account order over the limit when the group says so', function () {
    $this->group->update(['over_limit_action' => 'block']);

    expect(fn () => ($this->place)(10))->toThrow(ShopException::class)
        ->and(Order::query()->count())->toBe(0)
        ->and($this->product->fresh()->stock)->toBe(100);
});

it('sends an order to approval when there is an overdue debt', function () {
    $first = ($this->place)(1);
    $this->travel(31)->days();

    expect(app(CreditService::class)->overdue($this->account->fresh())['amount'])->toEqualDecimal($first->grand_total);

    $second = ($this->place)(1);
    expect($second->status)->toBe(OrderStatus::AwaitingApproval);
});

it('allocates collections to the oldest due orders first (FIFO)', function () {
    $first = ($this->place)(2);  // 240
    $second = ($this->place)(2); // 240

    app(CollectionService::class)->collect($this->account->fresh(), $this->admin, ['type' => 'cash', 'amount' => 300]);

    expect($first->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($second->fresh()->payment_status)->toBe(PaymentStatus::Partial)
        ->and(app(LedgerService::class)->balance($this->account->id))->toEqualDecimal('180.0');

    $dues = app(CreditService::class)->dues($this->account->fresh(), 60);
    expect($dues['upcoming'])->toHaveCount(1)
        ->and($dues['upcoming'][0]['order']['number'])->toBe($second->number);
});

it('saves credit terms on the dealer group and the dealer, and the ledger settings', function () {
    $this->actingAs($this->admin)->put(route('panel.shop.dealer-groups.update', $this->group->id), [
        'name' => 'Vadeli Bayiler', 'discount_rate' => '5',
        'payment_terms' => 'credit', 'credit_limit' => '2500,50', 'payment_term_days' => '45',
        'over_limit_action' => 'block', 'overdue_action' => 'allow', 'allowed_payment_methods' => ['on_account'],
    ])->assertSessionHasNoErrors();

    expect($this->group->fresh()->payment_term_days)->toBe(45)
        ->and($this->group->fresh()->allowed_payment_methods)->toBe(['on_account']);

    $this->actingAs($this->admin)->put(route('panel.shop.customers.type', $this->member->id), [
        'type' => 'dealer', 'dealer_group_id' => $this->group->id,
        'payment_terms' => '', 'credit_limit' => '750', 'payment_term_days' => '60',
    ])->assertSessionHasNoErrors();

    $terms = $this->account->fresh()->creditTerms();
    expect($terms->limit)->toEqualDecimal('750.0')
        ->and($terms->termDays)->toBe(60)
        ->and($terms->overLimit->value)->toBe('block');

    $this->actingAs($this->admin)->get(route('panel.members.show', $this->member->id))
        ->assertInertia(fn (Assert $page) => $page->where('user.data.effective_terms.terms', 'credit')->where('termOptions.methods', fn ($methods) => collect($methods)->pluck('value')->intersect(['bank_transfer', 'on_account'])->count() === 2));

    $this->actingAs($this->admin)->put(route('panel.shop.ledger-settings.update'), [
        'min_amounts' => ['bank_transfer' => '250', 'on_account' => ''], 'due_warning_days' => '10',
    ])->assertSessionHasNoErrors();

    expect(json_decode((string) ShopSettings::get('payment_min_amounts'), true))->toBe(['bank_transfer' => '250.00'])
        ->and(ShopSettings::bool('cheques_enabled'))->toBeFalse();
});
