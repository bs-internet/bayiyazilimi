<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Exceptions\Shop\ShopException;
use App\Models\BankAccount;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\OrderService;
use App\Services\Shop\ProductExporter;
use App\Services\Shop\ProductImporter;
use App\Services\Shop\ShippingService;
use App\Services\Shop\ShopSettings;
use App\Services\Shop\StockService;
use Database\Seeders\ShopSeeder;
use Illuminate\Support\Facades\Notification;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);

    $this->try = Currency::query()->where('code', 'TRY')->first();
    $this->carrier = Carrier::query()->where('code', 'YURTICI')->first();
    $this->admin = User::factory()->create();
    BankAccount::query()->create(['bank_name' => 'Test Bankası', 'account_holder' => 'Örnek A.Ş.', 'iban' => 'TR120006400000112345678901', 'status' => 'active']);

    app(ShippingService::class)->saveRates($this->carrier, [
        ['min_desi' => 0, 'max_desi' => 3, 'price' => 100],
        ['min_desi' => 3, 'max_desi' => 10, 'price' => 200],
    ], $this->admin);
    $this->carrier->update(['extra_desi_price' => 15]);

    $this->product = Product::create([
        'sku' => 'DESI-1', 'name' => 'Desi Ürünü', 'slug' => 'desi-urunu',
        'price' => 1000, 'currency_id' => $this->try->id, 'tax_rate' => 20,
        'sale_mode' => SaleMode::Cart, 'status' => Status::Active, 'desi' => 2,
    ]);

    app(StockService::class)->change($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 50, StockMovementType::In);
});

function writeXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return $path;
}

it('prices shipping by total desi with overflow per extra desi', function () {
    $shipping = app(ShippingService::class);
    $carrier = $this->carrier->fresh('rates');

    expect($shipping->priceFor($carrier, 1))->toEqualDecimal('100.0')
        ->and($shipping->priceFor($carrier, 3))->toEqualDecimal('100.0')
        ->and($shipping->priceFor($carrier, '3.5'))->toEqualDecimal('200.0')
        ->and($shipping->priceFor($carrier, '12.2'))->toEqualDecimal('245');

    $carrier->update(['prices_include_tax' => false]);
    expect($shipping->priceFor($carrier->fresh('rates'), 1))->toEqualDecimal('120.0');
});

it('applies carrier shipping to the cart and the order', function () {
    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 3); // 6 desi → 200 TL

    $totals = $cart->totals($this->try, $this->carrier->fresh('rates'));
    expect($totals['desi'])->toEqualDecimal('6.0')
        ->and($totals['shipping'])->toEqualDecimal('200.0')
        ->and($totals['total'])->toEqualDecimal('3800');

    $order = app(OrderService::class)->placeFromCart($cart, [
        'email' => 'a@example.com', 'phone' => '0555',
        'billing' => ['name' => 'A', 'address' => 'X', 'city' => 'Bursa', 'district' => 'Nilüfer'],
        'same_address' => true, 'payment_method' => 'bank_transfer', 'carrier_id' => $this->carrier->id,
    ], null, $this->try);

    expect($order->carrier_id)->toBe($this->carrier->id)
        ->and($order->cargo_company)->toBe('Yurtiçi Kargo')
        ->and($order->total_desi)->toEqualDecimal('6.0')
        ->and($order->shipping_total)->toBeMoney('200.00');
});

it('gives free shipping above the threshold', function () {
    ShopSettings::save(['free_shipping_threshold' => '2000']);

    $cart = Basket::cart();
    $cart->add($this->product->fresh(), 2);

    expect($cart->totals($this->try, $this->carrier->fresh('rates'))['shipping'])->toEqualDecimal('0.0');
});

it('imports carrier rates from excel in both layouts', function () {
    $shipping = app(ShippingService::class);

    $three = writeXlsx([['Desi Başlangıç', 'Desi Bitiş', 'Fiyat'], [0, 1, 90], [1, 5, '150,50'], [5, 20, 300]]);
    expect($shipping->importRates($this->carrier, $three, $this->admin))->toBe(3)
        ->and($this->carrier->rates()->where('max_desi', 5)->value('price'))->toEqualDecimal('150.5');

    $two = writeXlsx([['Desi', 'Fiyat'], [2, 80], [10, 160]]);
    expect($shipping->importRates($this->carrier, $two, $this->admin))->toBe(2)
        ->and($this->carrier->rates()->where('max_desi', 10)->value('min_desi'))->toEqualDecimal('2.0');

    $empty = writeXlsx([['Desi', 'Fiyat']]);
    expect(fn () => $shipping->importRates($this->carrier, $empty, $this->admin))->toThrow(ShopException::class);

    $template = $shipping->template($this->carrier);
    expect(is_file($template))->toBeTrue();

    @unlink($three);
    @unlink($two);
    @unlink($empty);
    @unlink($template);
});

it('exports products and re-imports the same file without data loss', function () {
    $this->product->update(['is_new' => true, 'sale_mode' => SaleMode::Cart, 'meta_title' => 'SEO Başlık']);

    $path = app(ProductExporter::class)->export();
    expect(is_file($path))->toBeTrue();

    $this->product->update(['is_new' => false, 'sale_mode' => SaleMode::Cart, 'meta_title' => null]);
    app(StockService::class)->setQuantity($this->product, Warehouse::query()->where('code', 'DEPO')->first(), 1);

    $result = app(ProductImporter::class)->import($path);
    $fresh = $this->product->fresh();

    expect($result['updated'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($fresh->is_new)->toBeTrue()
        ->and($fresh->sale_mode)->toBe(SaleMode::Cart)
        ->and($fresh->meta_title)->toBe('SEO Başlık')
        ->and($fresh->stock)->toBe(50);

    @unlink($path);
});
