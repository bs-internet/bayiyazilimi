<?php

declare(strict_types=1);

use App\Enums\Shop\CustomerType;
use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Shop\LedgerService;
use App\Services\Shop\OrderService;
use App\Services\Shop\StockService;
use App\Support\Payments\BaseGateway;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\PaymentStart;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    // Kanalın kendi ödeme sürücüsü: ödeme sayfasında sunulmaz, siparişi doğrudan onaylar.
    app(PaymentRegistry::class)->add(new class extends BaseGateway
    {
        public function key(): string
        {
            return 'test_channel';
        }

        public function label(): string
        {
            return 'Test Kanalı';
        }

        public function available(PaymentContext $context): bool
        {
            return false;
        }

        public function start(Order $order): PaymentStart
        {
            $order->forceFill(['status' => OrderStatus::Processing])->save();
            app(LedgerService::class)->recordOrder($order);

            return PaymentStart::none();
        }
    });

    $try = Currency::query()->where('code', 'TRY')->firstOrFail();
    $this->product = Product::query()->create([
        'sku' => 'EXT-1', 'name' => 'Kanal Ürünü', 'slug' => 'kanal-urunu', 'price' => 100,
        'currency_id' => $try->id, 'tax_rate' => 20, 'sale_mode' => SaleMode::Cart, 'status' => Status::Active,
    ]);
    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->firstOrFail(), 1, StockMovementType::In);

    $this->account = Account::query()->create(['code' => 'kanal-test', 'title' => 'Test Kanalı', 'type' => CustomerType::Dealer]);
});

test('an external channel order keeps the channel price, splits VAT out of it and deducts stock even when short', function () {
    $order = app(OrderService::class)->placeExternal(
        [['product' => $this->product, 'quantity' => 2, 'unit_gross' => '149.90']],
        [
            'customer_name' => 'Pazar Müşterisi', 'email' => 'magaza@example.com', 'phone' => '05550000000',
            'billing' => ['type' => 'individual', 'name' => 'Pazar Müşterisi', 'address' => 'Adres 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'shipping' => ['name' => 'Pazar Müşterisi', 'address' => 'Adres 1', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            'note' => 'Kanal siparişi 123',
        ],
        $this->account,
        'test_channel',
    );

    $order->refresh();
    $item = $order->items()->firstOrFail();

    expect($order->status)->toBe(OrderStatus::Processing)
        ->and($order->account_id)->toBe($this->account->id)
        ->and($order->user_id)->toBeNull()
        ->and($order->grand_total)->toBeMoney('299.80')
        ->and($order->tax_total)->toBeMoney('49.97')
        ->and($order->subtotal)->toBeMoney('249.83')
        ->and($item->line_total)->toBeMoney('299.80')
        ->and($order->stock_deducted)->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(-1)
        ->and($order->histories()->where('note', 'Kanal siparişi 123')->exists())->toBeTrue();
});

test('an unknown channel payment method is refused', function () {
    expect(fn () => app(OrderService::class)->placeExternal(
        [['product' => $this->product, 'quantity' => 1, 'unit_gross' => '10']],
        ['customer_name' => 'X', 'email' => 'x@example.com', 'phone' => '1', 'billing' => ['name' => 'X'], 'shipping' => ['name' => 'X']],
        $this->account,
        'yok',
    ))->toThrow(ShopException::class);
});
