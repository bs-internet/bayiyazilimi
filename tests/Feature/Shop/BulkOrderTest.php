<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Shop\StockMovementType;
use App\Enums\Status;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\Basket;
use App\Services\Shop\BulkOrderService;
use App\Services\Shop\StockService;
use App\Support\SpreadsheetRow;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    $this->withoutVite();
    $this->dealer = User::query()->where('email', 'user@user.com')->firstOrFail();
    $try = Currency::query()->where('code', 'TRY')->firstOrFail();
    $warehouse = Warehouse::query()->where('code', 'DEPO')->firstOrFail();

    $make = function (string $sku, ?string $barcode, SaleMode $mode = SaleMode::Cart) use ($try, $warehouse): Product {
        $product = Product::create([
            'sku' => $sku, 'barcode' => $barcode, 'name' => 'Ürün '.$sku, 'slug' => strtolower($sku),
            'price' => 10, 'currency_id' => $try->id, 'tax_rate' => 20, 'sale_mode' => $mode, 'status' => Status::Active, 'track_stock' => true,
        ]);
        app(StockService::class)->change($product, $warehouse, 20, StockMovementType::In);

        return $product;
    };

    $this->first = $make('TS-1', '8690000000101');
    $this->second = $make('TS-2', '8690000000102');
    $this->quoteOnly = $make('TS-3', null, SaleMode::Quote);
});

it('parses pasted Excel rows: skips the header, sums repeated codes and reports bad quantities', function () {
    $parsed = BulkOrderService::parseText("Stok kodu\tAdet\nTS-1\t2\nts-1\t3\n8690000000102;4\nTS-9\tx\n\n");

    expect($parsed['lines'])->toBe([['code' => 'TS-1', 'quantity' => 5], ['code' => '8690000000102', 'quantity' => 4]])
        ->and($parsed['errors'])->toHaveCount(1)
        ->and($parsed['errors'][0]['code'])->toBe('TS-9');
});

it('adds pasted lines by stock code or barcode and shows the rows it could not add', function () {
    $this->actingAs($this->dealer)
        ->post(route('app.shop.cart.import'), ['text' => "TS-1\t2\n8690000000102\t3\nTS-3\t1\nYOK-1\t1\nTS-2\t50"])
        ->assertRedirect();

    expect(Basket::cart()->quantityOf($this->first))->toBe(2)
        ->and(Basket::cart()->quantityOf($this->second))->toBe(3);

    $this->actingAs($this->dealer)->get(route('app.shop.cart.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bulkResult.added', 2)
            ->has('bulkResult.failed', 3)
            ->where('bulkResult.failed.0.code', 'TS-3')
            ->where('bulkResult.failed.1.reason', 'Ürün bulunamadı.'));

    // Sonuç yalnız bir kez gösterilir.
    $this->actingAs($this->dealer)->get(route('app.shop.cart.index'))
        ->assertInertia(fn (Assert $page) => $page->where('bulkResult', null));
});

it('reads an uploaded xlsx file and offers a template', function () {
    $path = tempnam(sys_get_temp_dir(), 'bulk').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(SpreadsheetRow::of(['Stok kodu / Barkod', 'Adet']));
    $writer->addRow(SpreadsheetRow::of([8690000000101, 4]));
    $writer->addRow(SpreadsheetRow::of(['TS-2', 1]));
    $writer->close();

    $this->actingAs($this->dealer)
        ->post(route('app.shop.cart.import'), ['file' => new UploadedFile($path, 'siparis.xlsx', null, null, true)])
        ->assertRedirect();

    expect(Basket::cart()->quantityOf($this->first))->toBe(4)
        ->and(Basket::cart()->quantityOf($this->second))->toBe(1);

    $this->actingAs($this->dealer)->get(route('app.shop.cart.import-template'))->assertOk()->assertDownload('toplu-siparis.xlsx');
});

it('requires a file or pasted rows', function () {
    $this->actingAs($this->dealer)->post(route('app.shop.cart.import'), [])->assertSessionHasErrors(['file', 'text']);
});
