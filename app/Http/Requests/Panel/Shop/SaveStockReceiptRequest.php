<?php

declare(strict_types=1);

namespace App\Http\Requests\Panel\Shop;

use App\Enums\Shop\ReceiptDocumentType;
use App\Enums\Status;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mal kabul: belge bilgileri + satırlar. Lot / seri takipli ürünlerde lot no,
 * seri numaraları ve (SKT takipliyse) son kullanma tarihi satır bazında doğrulanır.
 */
class SaveStockReceiptRequest extends FormRequest
{
    public const MAX_LINES = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')->where('status', Status::Active->value)],
            'document_type' => ['required', Rule::enum(ReceiptDocumentType::class)],
            'document_no' => ['required', 'string', 'max:64'],
            'document_date' => ['required', 'date', 'before_or_equal:today'],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('status', Status::Active->value)],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.location_id' => ['nullable', 'uuid', Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $this->input('warehouse_id'))->where('status', Status::Active->value)],
            'lines.*.lot_code' => ['nullable', 'string', 'max:64'],
            'lines.*.expires_at' => ['nullable', 'date'],
            'lines.*.serials' => ['nullable', 'string', 'max:100000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateDuplicateDocument($validator);
                $this->validateTracking($validator);
            },
        ];
    }

    /**
     * Seri numaralarını satır satır (virgül / boşluk da ayırıcı) ayrıştırır.
     *
     * @return array<int, string>
     */
    public static function parseSerials(?string $raw): array
    {
        $parts = preg_split('/[\s,;]+/u', trim((string) $raw)) ?: [];

        return array_values(array_filter(array_map(fn ($s) => mb_strtoupper(trim($s)), $parts), fn ($s) => $s !== ''));
    }

    private function validateDuplicateDocument(Validator $validator): void
    {
        $existing = StockReceipt::query()
            ->where('supplier_id', $this->input('supplier_id'))
            ->where('document_type', $this->input('document_type'))
            ->where('document_no', trim((string) $this->input('document_no')))
            ->value('number');

        if ($existing !== null) {
            $validator->errors()->add('document_no', __('Bu tedarikçinin bu belgesi daha önce kabul edilmiş (:existing).', ['existing' => $existing]));
        }
    }

    private function validateTracking(Validator $validator): void
    {
        $lines = (array) $this->input('lines', []);
        $products = Product::query()->whereKey(array_column($lines, 'product_id'))->get()->keyBy('id');
        $seenSerials = [];

        foreach ($lines as $i => $line) {
            /** @var Product $product */
            $product = $products[$line['product_id']];
            $key = "lines.{$i}";
            $expires = $line['expires_at'] ?? null;

            if (! $product->tracksLots()) {
                continue;
            }

            if ($product->tracksExpiry() && empty($expires)) {
                $validator->errors()->add("{$key}.expires_at", __(':sku için son kullanma tarihi gerekli.', ['sku' => $product->sku]));
            }

            if (! $product->tracksSerials()) {
                $code = mb_strtoupper(trim((string) ($line['lot_code'] ?? '')));

                if ($code === '') {
                    $validator->errors()->add("{$key}.lot_code", __(':sku için lot numarası gerekli.', ['sku' => $product->sku]));

                    continue;
                }

                $lot = StockLot::query()->where('product_id', $product->id)->where('code', $code)->first();

                if ($lot !== null && ! empty($expires) && $lot->expires_at?->toDateString() !== date('Y-m-d', strtotime((string) $expires))) {
                    $validator->errors()->add("{$key}.expires_at", __(':code lotu farklı bir son kullanma tarihiyle kayıtlı (:date).', ['code' => $code, 'date' => $lot->expires_at?->format('d.m.Y') ?? __('SKT yok')]));
                }

                continue;
            }

            $serials = self::parseSerials($line['serials'] ?? null);

            if (count($serials) !== (int) $line['quantity']) {
                $validator->errors()->add("{$key}.serials", __(':sku: seri numarası sayısı (:count) miktarla (:quantity) aynı olmalı.', ['sku' => $product->sku, 'count' => count($serials), 'quantity' => $line['quantity']]));

                continue;
            }

            $duplicates = array_diff_assoc($serials, array_unique($serials));

            foreach ($serials as $serial) {
                $seenSerials[$product->id][$serial] = ($seenSerials[$product->id][$serial] ?? 0) + 1;
            }

            if ($duplicates !== []) {
                $validator->errors()->add("{$key}.serials", __('Tekrarlanan seri numarası: :serials.', ['serials' => implode(', ', array_unique($duplicates))]));

                continue;
            }

            $inStock = LocationStock::query()
                ->where('product_id', $product->id)
                ->whereHas('lot', fn ($q) => $q->whereIn('code', $serials))
                ->with('lot:id,code')
                ->get()
                ->groupBy(fn (LocationStock $s) => $s->lot->code)
                ->filter(fn ($rows) => $rows->sum('quantity') > 0)
                ->keys()
                ->all();

            if ($inStock !== []) {
                $validator->errors()->add("{$key}.serials", __('Zaten stokta olan seri numarası: :serials.', ['serials' => implode(', ', $inStock)]));
            }
        }

        foreach ($seenSerials as $productId => $counts) {
            $repeated = array_keys(array_filter($counts, fn ($n) => $n > 1));

            if ($repeated !== [] && ! $validator->errors()->has('lines.*.serials')) {
                $validator->errors()->add('lines', __(':sku: aynı seri numarası birden çok satırda: :serials.', ['sku' => $products[$productId]->sku, 'serials' => implode(', ', $repeated)]));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => __('tedarikçi'),
            'document_type' => __('belge türü'),
            'document_no' => __('belge no'),
            'document_date' => __('belge tarihi'),
            'warehouse_id' => __('depo'),
            'lines' => __('satırlar'),
            'lines.*.product_id' => __('ürün'),
            'lines.*.quantity' => __('miktar'),
            'lines.*.location_id' => __('lokasyon'),
            'lines.*.lot_code' => __('lot no'),
            'lines.*.expires_at' => __('son kullanma tarihi'),
            'lines.*.serials' => __('seri numaraları'),
        ];
    }
}
