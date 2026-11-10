<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\LocationStock;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Çoklu sipariş toplama listesi: siparişlerin stoktan düşüldüğü depo ×
 * lokasyon × lot satırları toplama sırasına (koridor / raf / göz, Atanmamış en
 * sonda) dizilir; aynı yerdeki aynı ürün tek satırda toplanır, hangi siparişe
 * kaç adet gideceği yanında yazar. Stoktan düşülmemiş kalemler (stok takipsiz
 * ürün, düşülmemiş sipariş) "Lokasyonsuz" bölümünde listelenir.
 */
class PickListBuilder
{
    public const MAX_ORDERS = 100;

    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * @param  Collection<int, Order>  $orders  items yüklü
     * @return array{warehouses: array<int, array<string, mixed>>, unlocated: array<int, array<string, mixed>>, totals: array{lines: int, quantity: int}}
     */
    public function build(Collection $orders): array
    {
        $rows = [];
        $unlocated = [];

        foreach ($orders as $order) {
            $picks = $order->stock_deducted ? $this->stock->orderPicks($order) : collect();
            $picked = $picks->groupBy('product_id')->map(fn (Collection $g) => (int) $g->sum('quantity'));

            foreach ($picks as $pick) {
                if ($pick['quantity'] <= 0 || $pick['location'] === null) {
                    continue;
                }

                $key = implode('|', [$pick['warehouse']?->id, $pick['location']->id, $pick['product_id'], $pick['lot']?->id]);

                $rows[$key] ??= [
                    'warehouse' => $pick['warehouse'],
                    'location' => $pick['location'],
                    'product' => $pick['product'],
                    'lot' => $pick['lot'],
                    'quantity' => 0,
                    'orders' => [],
                ];
                $rows[$key]['quantity'] += $pick['quantity'];
                $rows[$key]['orders'][$order->number] = ($rows[$key]['orders'][$order->number] ?? 0) + $pick['quantity'];
            }

            // Düşülmemiş kalan (stok takipsiz ürün vb.).
            foreach ($order->items->groupBy('product_id') as $productId => $items) {
                $remaining = (int) $items->sum('quantity') - (int) ($picked[$productId] ?? 0);

                if ($remaining <= 0) {
                    continue;
                }

                /** @var OrderItem $item */
                $item = $items->first();
                $key = $productId ?: $item->sku;

                $unlocated[$key] ??= ['sku' => $item->sku, 'name' => $item->name, 'quantity' => 0, 'orders' => []];
                $unlocated[$key]['quantity'] += $remaining;
                $unlocated[$key]['orders'][$order->number] = ($unlocated[$key]['orders'][$order->number] ?? 0) + $remaining;
            }
        }

        $short = $this->shortages(collect($rows));

        $warehouses = collect($rows)
            ->groupBy(fn (array $r) => (string) $r['warehouse']?->id)
            ->map(function (Collection $group) use ($short) {
                $warehouse = $group->first()['warehouse'];

                $lines = $group
                    ->sortBy(fn (array $r) => implode('|', [
                        $r['location']->is_unassigned ? 1 : 0,
                        sprintf('%010d', $r['location']->sort_order),
                        $r['location']->code,
                        $r['product']?->name,
                        $r['lot']?->expires_at?->format('Ymd') ?? '99999999',
                        $r['lot']?->code,
                    ]))
                    ->map(fn (array $r) => [
                        'location' => $r['location']->label(),
                        'unassigned' => $r['location']->is_unassigned,
                        'sku' => $r['product']?->sku,
                        'barcode' => $r['product']?->barcode,
                        'name' => $r['product']?->name,
                        'unit' => $r['product']?->unit,
                        'lot' => $r['lot']?->code,
                        'expires_at' => $r['lot']?->expires_at?->toDateString(),
                        'quantity' => $r['quantity'],
                        'short' => isset($short[$this->stockKey($r)]),
                        'orders' => collect($r['orders'])->map(fn (int $q, string $n) => ['number' => $n, 'quantity' => $q])->values()->all(),
                    ])
                    ->values();

                return [
                    'id' => $warehouse?->id,
                    'name' => $warehouse ? __(':name (:code)', ['name' => $warehouse->name, 'code' => $warehouse->code]) : __('Depo silinmiş'),
                    'sort' => $warehouse?->sort_order ?? PHP_INT_MAX,
                    'lines' => $lines->all(),
                    'quantity' => (int) $lines->sum('quantity'),
                ];
            })
            ->sortBy('sort')
            ->map(fn (array $w) => collect($w)->except('sort')->all())
            ->values()
            ->all();

        $unlocatedRows = collect($unlocated)
            ->sortBy('name')
            ->map(fn (array $u) => [...$u, 'orders' => collect($u['orders'])->map(fn (int $q, string $n) => ['number' => $n, 'quantity' => $q])->values()->all()])
            ->values()
            ->all();

        return [
            'warehouses' => $warehouses,
            'unlocated' => $unlocatedRows,
            'totals' => [
                'lines' => count($rows) + count($unlocatedRows),
                'quantity' => (int) collect($rows)->sum('quantity') + (int) collect($unlocatedRows)->sum('quantity'),
            ],
        ];
    }

    /**
     * Stok yetmeden düşülmüş (yerinde eksiye düşmüş) satırlar: rafta bulunmayabilir.
     *
     * @param  Collection<string, array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function shortages(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        return LocationStock::query()
            ->whereIn('product_id', $rows->pluck('product.id')->filter()->unique())
            ->whereIn('warehouse_location_id', $rows->pluck('location.id')->unique())
            ->where('quantity', '<', 0)
            ->get(['product_id', 'warehouse_location_id', 'stock_lot_id'])
            ->mapWithKeys(fn (LocationStock $s) => ["{$s->product_id}|{$s->warehouse_location_id}|{$s->stock_lot_id}" => true])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stockKey(array $row): string
    {
        return "{$row['product']?->id}|{$row['location']->id}|{$row['lot']?->id}";
    }
}
