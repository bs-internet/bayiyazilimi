<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Enums\Shop\StockMovementType;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shop\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * REST API toplu stok eşitleme: her satırda ürünün bir depodaki miktarı mutlak
 * değere getirilir; fark StockService::setQuantity() ile "Sayım Düzeltme"
 * hareketi olarak yazılır (not: "API"). İstek ya tümüyle uygulanır ya hiç.
 *
 * Lot / seri takipli ürünler reddedilir: miktarları lot bazında tutulur ve
 * toplam miktardan hangi lotun değiştiği çıkarılamaz.
 */
class StockSyncService
{
    public const NOTE = 'API';

    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * @param  list<array{sku: string, warehouse: string, quantity: int}>  $items
     * @return list<array{sku: string, warehouse: string, quantity: int, changed: bool}>
     */
    public function sync(array $items, User $causer): array
    {
        $products = Product::query()->whereIn('sku', array_unique(array_column($items, 'sku')))->get()->keyBy(fn (Product $p): string => mb_strtolower($p->sku));
        $warehouses = Warehouse::query()->whereIn('code', array_unique(array_column($items, 'warehouse')))->get()->keyBy(fn (Warehouse $w): string => mb_strtolower($w->code));

        $errors = [];
        $seen = [];
        $rows = [];

        foreach ($items as $index => $item) {
            $product = $products->get(mb_strtolower($item['sku']));
            $warehouse = $warehouses->get(mb_strtolower($item['warehouse']));
            $key = mb_strtolower($item['sku'])."\0".mb_strtolower($item['warehouse']);

            if ($product === null) {
                $errors["items.{$index}.sku"] = __(':sku kodlu ürün bulunamadı.', ['sku' => $item['sku']]);
            } elseif ($product->tracksLots()) {
                $errors["items.{$index}.sku"] = __(':sku ürünü lot / seri takipli; stoğu API ile toplam miktar olarak yazılamaz.', ['sku' => $item['sku']]);
            }

            if ($warehouse === null) {
                $errors["items.{$index}.warehouse"] = __(':code kodlu depo bulunamadı.', ['code' => $item['warehouse']]);
            }

            if (isset($seen[$key])) {
                $errors["items.{$index}.sku"] = __('Aynı ürün ve depo istekte birden fazla kez geçiyor.');
            }

            $seen[$key] = true;

            if ($product !== null && $warehouse !== null) {
                $rows[] = [$product, $warehouse, (int) $item['quantity']];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Ürün kilitleri sabit sırayla alınır: eşzamanlı iki toplu istek kilitlenmesin.
        usort($rows, fn (array $a, array $b): int => [$a[0]->id, $a[1]->id] <=> [$b[0]->id, $b[1]->id]);

        return DB::transaction(function () use ($rows, $causer): array {
            $result = [];

            foreach ($rows as [$product, $warehouse, $quantity]) {
                $movement = $this->stock->setQuantity($product, $warehouse, $quantity, self::NOTE, $causer, StockMovementType::Adjust);

                $result[] = [
                    'sku' => $product->sku,
                    'warehouse' => $warehouse->code,
                    'quantity' => $quantity,
                    'changed' => $movement !== null,
                ];
            }

            return $result;
        });
    }
}
