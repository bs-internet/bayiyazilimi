<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Product;
use App\Support\SpreadsheetRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel ile toplu sipariş: stok kodu ya da barkod + adet satırlarını (Excel /
 * CSV dosyası ya da Excel'den yapıştırılan metin) sepete ekler. Ürünler tek
 * sorguda eşleşir; sepet kuralları (satış biçimi, stok, en az / en çok adet)
 * QuickOrderService ile aynen uygulanır. Aynı ürün birden çok satırdaysa
 * adetler toplanır.
 */
final class BulkOrderService
{
    public const MAX_LINES = 1000;

    public function __construct(
        private readonly QuickOrderService $quick,
    ) {}

    /**
     * Excel'den kopyalanan metin: her satırda kod ve adet (sekme, noktalı virgül
     * ya da virgülle ayrılmış).
     *
     * @return array{lines: array<int, array{code: string, quantity: int}>, errors: array<int, array{code: string, reason: string}>}
     */
    public static function parseText(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $rows[] = preg_split('/\t|;|,(?=\s*\d+\s*$)/', trim($line)) ?: [];
        }

        return self::parseRows($rows);
    }

    /**
     * Excel (.xlsx) ya da CSV dosyası: ilk sayfanın ilk iki sütunu.
     *
     * @return array{lines: array<int, array{code: string, quantity: int}>, errors: array<int, array{code: string, reason: string}>}
     */
    public static function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'xlsx') {
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = self::csvDelimiter((string) file_get_contents($file->getRealPath(), length: 4096));
            $reader = new CsvReader($options);
        } else {
            $reader = new XlsxReader;
        }

        $rows = [];
        $reader->open($file->getRealPath());

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();

                    if (count($rows) > self::MAX_LINES + 1) {
                        break;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return self::parseRows($rows);
    }

    /**
     * Satırları koda göre toplar. Adet sütunu sayı olmayan ilk satır başlık sayılır.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{lines: array<int, array{code: string, quantity: int}>, errors: array<int, array{code: string, reason: string}>}
     */
    public static function parseRows(array $rows): array
    {
        $lines = [];
        $errors = [];
        $first = true;

        foreach ($rows as $row) {
            $code = self::code($row[0] ?? null);
            $rawQuantity = trim((string) self::scalar($row[1] ?? ''));

            if ($code === '' && $rawQuantity === '') {
                continue;
            }

            if ($first && ! is_numeric(str_replace(',', '.', $rawQuantity))) {
                $first = false;

                continue;
            }

            $first = false;
            $quantity = filter_var($rawQuantity === '' ? '1' : str_replace([',00', '.00'], '', $rawQuantity), FILTER_VALIDATE_INT);

            if ($code === '') {
                $errors[] = ['code' => '', 'reason' => __('Stok kodu ya da barkod boş.')];

                continue;
            }

            if ($quantity === false || $quantity < 1 || $quantity > 100000) {
                $errors[] = ['code' => $code, 'reason' => __('Adet geçersiz: :quantity', ['quantity' => $rawQuantity])];

                continue;
            }

            $key = mb_strtolower($code);
            $lines[$key] = ['code' => $lines[$key]['code'] ?? $code, 'quantity' => ($lines[$key]['quantity'] ?? 0) + $quantity];

            if (count($lines) > self::MAX_LINES) {
                $errors[] = ['code' => $code, 'reason' => __('En fazla :max satır yüklenebilir; sonrası alınmadı.', ['max' => self::MAX_LINES])];
                array_pop($lines);

                break;
            }
        }

        return ['lines' => array_values($lines), 'errors' => $errors];
    }

    /**
     * Satırları sepete ekler. Eklenemeyen her satır nedeniyle döner.
     *
     * @param  array<int, array{code: string, quantity: int}>  $lines
     * @return array{added: array<int, array{sku: string, name: string, quantity: int}>, failed: array<int, array{code: string, quantity: ?int, reason: string}>}
     */
    public function add(array $lines): array
    {
        $result = ['added' => [], 'failed' => []];
        $codes = array_map(fn (array $line) => $line['code'], $lines);
        $bySku = [];
        $byBarcode = [];

        foreach (array_chunk($codes, 500) as $chunk) {
            Product::query()->visible()
                ->where(fn ($q) => $q->whereIn('sku', $chunk)->orWhereIn('barcode', $chunk))
                ->get()
                ->each(function (Product $product) use (&$bySku, &$byBarcode): void {
                    $bySku[mb_strtolower($product->sku)] = $product;

                    if (filled($product->barcode)) {
                        $byBarcode[mb_strtolower((string) $product->barcode)] ??= $product;
                    }
                });
        }

        $cart = Basket::cart();

        foreach ($lines as $line) {
            $key = mb_strtolower($line['code']);
            $product = $bySku[$key] ?? $byBarcode[$key] ?? null;

            if ($product === null) {
                $result['failed'][] = ['code' => $line['code'], 'quantity' => $line['quantity'], 'reason' => __('Ürün bulunamadı.')];

                continue;
            }

            $error = $this->quick->add($product, $line['quantity'], $cart);

            $error === null
                ? $result['added'][] = ['sku' => $product->sku, 'name' => $product->name, 'quantity' => $line['quantity']]
                : $result['failed'][] = ['code' => $line['code'], 'quantity' => $line['quantity'], 'reason' => $error];
        }

        return $result;
    }

    /** Boş şablon: başlık ve iki örnek satır. Dosya yolunu döner. */
    public static function template(): string
    {
        $path = storage_path('app/private/toplu-siparis-'.uniqid().'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(SpreadsheetRow::of([__('Stok kodu / Barkod'), __('Adet')], (new Style)->withFontBold(true)));
        $writer->addRow(SpreadsheetRow::of(['URUN-001', 10]));
        $writer->addRow(SpreadsheetRow::of(['8690000000011', 5]));
        $writer->close();

        return $path;
    }

    /** Hücre değeri metne: Excel'in sayı olarak tuttuğu barkodlar bilimsel gösterime düşmez. */
    private static function code(mixed $value): string
    {
        return trim((string) self::scalar($value));
    }

    private static function scalar(mixed $value): string|int|float
    {
        return match (true) {
            is_float($value) && floor($value) === $value => number_format($value, 0, '', ''),
            is_int($value), is_float($value), is_string($value) => $value,
            $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }

    private static function csvDelimiter(string $sample): string
    {
        $counts = ['\t' => substr_count($sample, "\t"), ';' => substr_count($sample, ';'), ',' => substr_count($sample, ',')];
        arsort($counts);

        return match (array_key_first($counts)) {
            '\t' => "\t",
            ';' => ';',
            default => ',',
        };
    }
}
