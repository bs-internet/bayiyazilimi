<?php

declare(strict_types=1);

use App\Enums\Shop\SaleMode;
use App\Enums\Status;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\ProductExporter;
use App\Services\Shop\ProductImporter;
use App\Services\Shop\ProductSheet;
use App\Support\SpreadsheetRow;
use Database\Seeders\ShopSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @param  list<list<mixed>>  $rows  ilk satır başlık
 */
function productSheet(array $rows): string
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

/**
 * @return list<list<mixed>>
 */
function readSheet(string $path, int $sheetIndex = 0): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    $i = 0;

    foreach ($reader->getSheetIterator() as $sheet) {
        if ($i++ === $sheetIndex) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
    }

    $reader->close();

    return $rows;
}

beforeEach(function () {
    Notification::fake();
    $this->seed(ShopSeeder::class);
    Warehouse::query()->create(['code' => 'D2', 'name' => 'İkinci Depo', 'is_sellable' => true, 'status' => Status::Active, 'sort_order' => 2]);
    $this->importer = app(ProductImporter::class);
});

it('imports new products from the turkish template', function () {
    $path = productSheet([
        ['Stok Kodu*', 'Ürün Adı*', 'Marka', 'Kategori', 'Fiyat', 'İndirimsiz Fiyat', 'Para Birimi', 'KDV (%)', 'Satış Biçimi', 'Yeni Ürün', 'Öne Çıkan 1', 'Açıklama', 'Stok: DEPO', 'Stok: D2'],
        ['A-1', 'Deneme Ürün', 'Örnek Marka', 'Temizlik > Deterjanlar', '1.249,90', 1500, 'EUR', 20, 'Sepet', 'Evet', 'Konsantre', '<p>Açıklama</p>', 5, 2],
        ['B-2', 'Teklif Ürünü', '', '', '', '', '', '', '', '', '', '', 0, 1],
        ['C-3', 'Yüzdeli KDV', '', '', 10, '', 'tl', 0.1, 'teklif', 'hayır', '', '', '', ''],
    ]);

    $result = $this->importer->import($path);

    expect($result['created'])->toBe(3)->and($result['errors'])->toBe([]);

    $a = Product::query()->where('sku', 'A-1')->with(['brand', 'category', 'currency'])->first();
    expect($a->name)->toBe('Deneme Ürün')
        ->and($a->brand->name)->toBe('Örnek Marka')
        ->and($a->category->fullPath())->toBe('Temizlik > Deterjanlar')
        ->and($a->price)->toEqualDecimal('1249.9')
        ->and($a->compare_price)->toEqualDecimal('1500.0')
        ->and($a->currency->code)->toBe('EUR')
        ->and($a->tax_rate)->toEqualDecimal('20.0')
        ->and($a->sale_mode)->toBe(SaleMode::Cart)
        ->and($a->status)->toBe(Status::Active)
        ->and($a->is_new)->toBeTrue()
        ->and($a->highlights)->toBe(['Konsantre'])
        ->and($a->unit)->toBe('Adet')
        ->and($a->stock)->toBe(7);

    // Fiyatsız yeni ürün teklif ile satılır; para birimi TRY, varsayılan KDV.
    $b = Product::query()->where('sku', 'B-2')->with('currency')->first();
    expect($b->sale_mode)->toBe(SaleMode::Quote)
        ->and($b->currency->code)->toBe('TRY')
        ->and($b->brand_id)->toBeNull();

    $c = Product::query()->where('sku', 'C-3')->with('currency')->first();
    expect($c->tax_rate)->toEqualDecimal('10.0')
        ->and($c->currency->code)->toBe('TRY')
        ->and($c->sale_mode)->toBe(SaleMode::Quote);

    // Tekrar içe aktarım günceller, çoğaltmaz.
    expect($this->importer->import($path)['updated'])->toBe(3)
        ->and(Product::query()->count())->toBe(3);

    @unlink($path);
});

it('updates only the columns present in the file', function () {
    $this->importer->import($first = productSheet([
        ['Stok Kodu', 'Ürün Adı', 'Marka', 'Fiyat', 'Barkod', 'Öne Çıkan 1', 'Öne Çıkan 2', 'Stok: DEPO'],
        ['A-1', 'İlk Ad', 'Marka', 100, '869000', 'Bir', 'İki', 10],
    ]));

    $result = $this->importer->import($second = productSheet([
        ['stok kodu', 'FİYAT', 'Ürün Adı', 'Öne Çıkan 2', 'Stok: DEPO'],
        ['A-1', '150,5', '', 'Yeni iki', ''],
    ]));

    $product = Product::query()->where('sku', 'A-1')->with('brand')->first();

    expect($result['updated'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($product->price)->toEqualDecimal('150.5')
        ->and($product->name)->toBe('İlk Ad')
        ->and($product->brand->name)->toBe('Marka')
        ->and($product->barcode)->toBe('869000')
        ->and($product->highlights)->toBe(['Bir', 'Yeni iki'])
        ->and($product->stock)->toBe(10);

    @unlink($first);
    @unlink($second);
});

it('reports invalid rows without stopping the import', function () {
    $path = productSheet([
        ['Stok Kodu', 'Ürün Adı', 'Fiyat', 'Para Birimi', 'Satış Biçimi', 'Durum', 'Bilinmeyen', 'Stok: YOK'],
        ['A-1', 'Geçerli', 10, 'TRY', 'Sepet', 'Aktif', 'x', 1],
        ['B-2', '', 10, '', '', '', '', ''],
        ['C-3', 'Kötü fiyat', 'on lira', '', '', '', '', ''],
        ['D-4', 'Kötü kur', 10, 'XYZ', '', '', '', ''],
        ['E-5', 'Kötü biçim', 10, '', 'Hemen', '', '', ''],
        ['F-6', 'Kötü durum', 10, '', '', 'Belki', '', ''],
        ['', 'Kodsuz', 10, '', '', '', '', ''],
    ]);

    $result = $this->importer->import($path);
    $errors = implode("\n", $result['errors']);

    expect($result['created'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and(Product::query()->pluck('sku')->all())->toBe(['A-1'])
        ->and($errors)->toContain('Tanınmayan sütunlar atlandı: Bilinmeyen')
        ->and($errors)->toContain('"Stok: YOK" sütunu atlandı')
        ->and($errors)->toContain('Satır 3 (B-2): Ürün Adı boş.')
        ->and($errors)->toContain('Satır 4 (C-3): Fiyat "on lira" sayı değil.')
        ->and($errors)->toContain('Para Birimi "XYZ" tanımlı değil')
        ->and($errors)->toContain('Satış Biçimi "Hemen" geçersiz')
        ->and($errors)->toContain('Durum "Belki" geçersiz')
        ->and($errors)->toContain('Satır 8: Stok Kodu boş');

    @unlink($path);
});

it('rejects files that are not in the template format', function () {
    $path = productSheet([
        ['ProductID', 'SKU', 'ModelName'],
        [1, 'A-1', 'Eski biçim'],
    ]);

    $result = $this->importer->import($path);

    expect($result['created'])->toBe(0)
        ->and($result['errors'][0])->toContain('"Stok Kodu" sütunu bulunamadı');

    @unlink($path);
});

it('downloads images from the image columns only when asked', function () {
    Storage::fake('public');
    Http::fake(['ornek.test/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='), 200)]);

    $path = productSheet([
        ['Stok Kodu', 'Ürün Adı', 'Fiyat', 'Görsel 1', 'Görsel 2'],
        ['A-1', 'Görselli', 10, 'https://ornek.test/a.jpg', 'yerel/yol.jpg'],
    ]);

    expect($this->importer->import($path)['images'])->toBe(0);

    $result = $this->importer->import($path, ['download_images' => true]);
    $product = Product::query()->where('sku', 'A-1')->first();

    expect($result['images'])->toBe(1)
        ->and($product->images()->count())->toBe(1);

    @unlink($path);
});

it('does not fetch internal addresses or keep non-image responses', function () {
    Storage::fake('public');
    Http::fake(['ornek.test/*' => Http::response('<html>iç servis</html>', 200), '*' => Http::response('gizli', 200)]);

    $path = productSheet([
        ['Stok Kodu', 'Ürün Adı', 'Fiyat', 'Görsel 1', 'Görsel 2'],
        ['A-2', 'Görselli', 10, 'http://169.254.169.254/latest/meta-data', 'https://ornek.test/a.jpg'],
    ]);

    expect($this->importer->import($path, ['download_images' => true])['images'])->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));

    @unlink($path);
});

it('writes user text starting with = as text, not as a formula', function () {
    $row = SpreadsheetRow::of(['=HYPERLINK("http://kotu.test")', 'Normal', 5]);

    expect($row->cells[0])->toBeInstanceOf(StringCell::class)
        ->and($row->cells[0]->getValue())->toBe('=HYPERLINK("http://kotu.test")')
        ->and($row->cells[2]->getValue())->toBe(5);
});

it('exports in the template format and re-imports the same file', function () {
    $this->importer->import($source = productSheet([
        ['Stok Kodu', 'Ürün Adı', 'Kategori', 'Fiyat', 'Para Birimi', 'Satış Biçimi', 'Ücretsiz Kargo', 'SEO Başlık', 'Stok: DEPO', 'Stok: D2'],
        ['A-1', 'Ürün', 'Üst > Alt', 100, 'TRY', 'Teklif', 'Evet', 'SEO', 4, 6],
    ]));

    $path = app(ProductExporter::class)->export();
    $rows = readSheet($path);

    expect($rows[0])->toBe(ProductSheet::headers(Warehouse::query()->ordered()->get()))
        ->and($rows[1][0])->toBe('A-1')
        ->and(readSheet($path, 1)[0])->toBe(['Sütun', 'Zorunlu', 'Açıklama', 'Örnek']);

    Product::query()->where('sku', 'A-1')->update(['meta_title' => null, 'free_shipping' => false, 'sale_mode' => SaleMode::Cart]);

    $result = $this->importer->import($path);
    $product = Product::query()->where('sku', 'A-1')->with('category')->first();

    expect($result['updated'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($product->meta_title)->toBe('SEO')
        ->and($product->free_shipping)->toBeTrue()
        ->and($product->sale_mode)->toBe(SaleMode::Quote)
        ->and($product->category->fullPath())->toBe('Üst > Alt')
        ->and($product->stock)->toBe(10);

    @unlink($source);
    @unlink($path);
});

it('serves the blank template and imports uploads from the panel', function () {
    $this->withoutVite();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('panel.shop.import.template'));
    $response->assertOk()->assertDownload('urun-sablonu.xlsx');

    $template = $response->baseResponse->getFile()->getPathname();
    expect(readSheet($template))->toHaveCount(1)
        ->and(readSheet($template)[0])->toContain('Stok Kodu*', 'Stok: DEPO', 'Stok: D2');

    $path = productSheet([
        ['Stok Kodu', 'Ürün Adı', 'Fiyat'],
        ['P-1', 'Panelden', 25],
    ]);

    $this->actingAs($admin)
        ->post(route('panel.shop.import.store'), [
            'file' => new UploadedFile($path, 'urunler.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'update_existing' => '1',
            'update_stock' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('import_result', fn (array $r) => $r['created'] === 1 && $r['errors'] === []);

    expect(Product::query()->where('sku', 'P-1')->exists())->toBeTrue();

    @unlink($path);
});

it('imports and exports the critical stock threshold', function () {
    $this->importer->import(productSheet([
        ['Stok Kodu*', 'Ürün Adı*', 'Kritik Stok'],
        ['KS-1', 'Eşikli Ürün', 12],
        ['KS-2', 'Eşiksiz Ürün', ''],
    ]));

    expect(Product::query()->where('sku', 'KS-1')->value('min_stock'))->toBe(12)
        ->and(Product::query()->where('sku', 'KS-2')->value('min_stock'))->toBeNull();

    // Boş hücre ürünün eşiğini kaldırır; sütun yoksa dokunulmaz.
    $this->importer->import(productSheet([['Stok Kodu*', 'Fiyat'], ['KS-1', 10]]));
    expect(Product::query()->where('sku', 'KS-1')->value('min_stock'))->toBe(12);

    $this->importer->import(productSheet([['Stok Kodu*', 'Kritik Stok'], ['KS-1', '']]));
    expect(Product::query()->where('sku', 'KS-1')->value('min_stock'))->toBeNull();

    Product::query()->where('sku', 'KS-2')->update(['min_stock' => 7]);
    $rows = readSheet(app(ProductExporter::class)->export());
    $col = array_search('Kritik Stok', $rows[0], true);
    $row = collect($rows)->first(fn (array $r) => $r[0] === 'KS-2');

    expect($col)->not->toBeFalse()->and($row[$col])->toBe(7);
});

it('brings a deleted product back when its code is imported again', function () {
    $this->importer->import(productSheet([
        ['Stok Kodu*', 'Ürün Adı*', 'Fiyat'],
        ['DEL-1', 'Silinecek Ürün', 100],
    ]));
    Product::query()->where('sku', 'DEL-1')->firstOrFail()->delete();

    $result = $this->importer->import(productSheet([
        ['Stok Kodu*', 'Ürün Adı*', 'Fiyat'],
        ['DEL-1', 'Geri Gelen Ürün', 150],
    ]), ['update_existing' => false]);

    $product = Product::query()->where('sku', 'DEL-1')->first();

    expect($result['created'])->toBe(1)
        ->and($product)->not->toBeNull()
        ->and($product->name)->toBe('Geri Gelen Ürün')
        ->and($product->price)->toEqualDecimal('150');
});
