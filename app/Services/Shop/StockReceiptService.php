<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\StockMovementType;
use App\Events\Shop\StockReceivedEvent;
use App\Http\Requests\Panel\Shop\SaveStockReceiptRequest;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockReceipt;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Support\Facades\DB;

/**
 * Mal kabul: tedarikçi belgesine dayanan stok girişi. Her satır bir (seri
 * takipli üründe her seri için bir) "Giriş" hareketi yazar; hareketlerin
 * referansı mal kabul kaydıdır.
 */
class StockReceiptService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $data  SaveStockReceiptRequest ile doğrulanmış veri
     */
    public function create(array $data, User $user): StockReceipt
    {
        $receipt = DB::transaction(function () use ($data, $user) {
            $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

            $receipt = StockReceipt::query()->create([
                'number' => OrderService::nextNumber('MK', 'stock_receipts'),
                'supplier_id' => $data['supplier_id'],
                'document_type' => $data['document_type'],
                'document_no' => trim((string) $data['document_no']),
                'document_date' => $data['document_date'],
                'warehouse_id' => $warehouse->id,
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);

            $products = Product::query()->whereKey(array_column($data['lines'], 'product_id'))->get()->keyBy('id');
            $locations = WarehouseLocation::query()->whereKey(array_filter(array_column($data['lines'], 'location_id')))->get()->keyBy('id');
            $note = __('Mal kabul :number', ['number' => $receipt->number]);

            foreach ($data['lines'] as $line) {
                /** @var Product $product */
                $product = $products[$line['product_id']];
                $location = isset($line['location_id']) ? $locations[$line['location_id']] : null;
                $expires = $product->tracksLots() && ! empty($line['expires_at']) ? $line['expires_at'] : null;

                if ($product->tracksSerials()) {
                    foreach (SaveStockReceiptRequest::parseSerials($line['serials'] ?? null) as $serial) {
                        $this->stock->change($product, $warehouse, 1, StockMovementType::In, $note, $user, $receipt, false, $location, StockLot::resolve($product, $serial, $expires));
                    }

                    continue;
                }

                $lot = $product->tracksLots() ? StockLot::resolve($product, (string) $line['lot_code'], $expires) : null;

                $this->stock->change($product, $warehouse, (int) $line['quantity'], StockMovementType::In, $note, $user, $receipt, false, $location, $lot);
            }

            return $receipt;
        });

        StockReceivedEvent::dispatch($receipt, $user);

        return $receipt;
    }
}
