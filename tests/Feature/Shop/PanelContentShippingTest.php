<?php

declare(strict_types=1);

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\RateSource;
use App\Enums\Status;
use App\Models\Brand;
use App\Models\Carrier;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Shop\ShippingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function contentTestCurrency(): Currency
{
    return Currency::query()->updateOrCreate(['code' => 'TRY'], [
        'name' => 'TL', 'symbol' => '₺', 'decimal_places' => 2,
        'thousands_separator' => '.', 'decimal_separator' => ',',
        'exchange_rate' => 1, 'rate_source' => RateSource::Manual,
        'status' => Status::Active, 'sort_order' => 1,
    ]);
}

function contentTestProduct(Currency $currency, array $attributes = []): Product
{
    return Product::query()->create(array_merge([
        'sku' => 'SKU-'.fake()->unique()->numerify('####'),
        'name' => 'Test Ürün',
        'slug' => 'test-urun-'.fake()->unique()->numerify('####'),
        'price' => 100,
        'currency_id' => $currency->id,
        'tax_rate' => 20,
        'sale_mode' => 'cart',
        'status' => Status::Active,
    ], $attributes));
}

function contentTestOrder(array $attributes = []): Order
{
    return Order::query()->create(array_merge([
        'number' => 'S'.fake()->unique()->numerify('######'),
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'bank_transfer',
        'customer_name' => 'Ali',
        'email' => 'ali@example.com',
        'phone' => '555',
        'billing_address' => [],
        'shipping_address' => [],
    ], $attributes));
}

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->admin = User::factory()->admin()->create();
});

test('content and shipping screens render', function (string $route, string $component) {
    $this->actingAs($this->admin)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['panel.shop.carriers.index', 'panel/tools/definitions/carriers/index'],
]);

test('admin can manage carriers and desi rates manually and from excel', function () {
    $this->actingAs($this->admin)->post(route('panel.shop.carriers.store'), [
        'code' => 'testkargo',
        'name' => 'Yurtiçi Kargo',
        'extra_desi_price' => '15',
        'status' => 'active',
        'prices_include_tax' => '1',
    ])->assertRedirect();

    $carrier = Carrier::query()->where('code', 'TESTKARGO')->firstOrFail();

    $this->actingAs($this->admin)->put(route('panel.shop.carriers.rates.update', $carrier), [
        'rates' => [
            ['min_desi' => '0', 'max_desi' => '1', 'price' => '100'],
            ['min_desi' => '1', 'max_desi' => '5', 'price' => '150'],
        ],
    ])->assertSessionHasNoErrors();

    expect($carrier->rates()->count())->toBe(2)
        ->and(app(ShippingService::class)->priceFor($carrier->fresh(), 3))->toEqualDecimal('150.0')
        ->and(app(ShippingService::class)->priceFor($carrier->fresh(), 7))->toEqualDecimal('180.0');

    $path = tempnam(sys_get_temp_dir(), 'desi').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Desi', 'Fiyat']));
    $writer->addRow(Row::fromValues([2, '120,50']));
    $writer->addRow(Row::fromValues([10, '1.250,00']));
    $writer->close();

    $this->actingAs($this->admin)->post(route('panel.shop.carriers.rates.import', $carrier), [
        'file' => new UploadedFile($path, 'desi.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertSessionHasNoErrors();

    $rates = $carrier->rates()->get();
    expect($rates)->toHaveCount(2)
        ->and($rates[0]->min_desi)->toEqualDecimal('0.0')
        ->and($rates[1]->min_desi)->toEqualDecimal('2.0')
        ->and($rates[1]->price)->toEqualDecimal('1250.0');

    $this->actingAs($this->admin)
        ->get(route('panel.shop.carriers.edit', $carrier))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('panel/tools/definitions/carriers/edit')->has('carrier.rates', 2));

    $this->actingAs($this->admin)
        ->get(route('panel.shop.carriers.template', ['carrier' => $carrier->id]))
        ->assertOk()
        ->assertDownload('desi-fiyatlari-testkargo.xlsx');

    @unlink($path);
});

test('carrier used in an order cannot be deleted', function () {
    $carrier = Carrier::query()->create(['code' => 'TEST-ARAS', 'name' => 'Aras', 'status' => Status::Active]);
    contentTestOrder(['carrier_id' => $carrier->id]);

    $this->actingAs($this->admin)->delete(route('panel.shop.carriers.destroy', $carrier));

    expect(Carrier::query()->whereKey($carrier->id)->exists())->toBeTrue();
});

test('order status form sets the carrier from the select', function () {
    $carrier = Carrier::query()->create([
        'code' => 'TEST-MNG', 'name' => 'MNG Kargo', 'status' => Status::Active,
        'tracking_url' => 'https://takip.example/{code}',
    ]);
    $order = contentTestOrder([
        'status' => OrderStatus::Processing,
        'payment_status' => PaymentStatus::Paid,
        'stock_deducted' => true,
    ]);

    $this->actingAs($this->admin)->patch(route('panel.shop.orders.status', $order), [
        'status' => 'shipped',
        'carrier_id' => $carrier->id,
        'tracking_number' => 'ABC123',
    ])->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->carrier_id)->toBe($carrier->id)
        ->and($order->cargo_company)->toBe('MNG Kargo')
        ->and($order->tracking_number)->toBe('ABC123');

    $this->actingAs($this->admin)
        ->get(route('panel.shop.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.tracking_link', 'https://takip.example/ABC123')
            ->has('carriers'));
});

test('admin can export filtered products to excel', function () {
    $currency = contentTestCurrency();
    $brand = Brand::query()->create(['name' => 'Fein', 'slug' => 'fein']);
    contentTestProduct($currency, ['sku' => 'EXP-1', 'brand_id' => $brand->id]);
    contentTestProduct($currency, ['sku' => 'EXP-2']);

    $response = $this->actingAs($this->admin)
        ->get(route('panel.shop.export.products', ['brand' => $brand->id]))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $skus = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $skus[] = $row->toArray()[0];
        }
        break;
    }
    $reader->close();

    expect($skus)->toBe(['Stok Kodu*', 'EXP-1']);
});
