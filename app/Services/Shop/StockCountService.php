<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\StockCountStatus;
use App\Enums\Shop\StockMovementType;
use App\Events\Shop\StockCountClosedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sayım oturumu.
 *
 * - Bir depoda aynı anda örtüşen iki açık sayım olamaz (tüm depo ya da aynı lokasyon).
 * - Satır = ürün × lokasyon × lot. Her güncellemede `expected` o anki sistem
 *   miktarıyla yenilenir; onayda `counted - expected` kadar düzeltilir. Böylece
 *   sayımdan sonra (onaydan önce) yapılan satışlar geri alınmaz.
 * - "Sayılmayanları sıfırla" seçilirse kapsamdaki sayılmamış stok satırları 0'a çekilir.
 */
class StockCountService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function open(Warehouse $warehouse, ?WarehouseLocation $location, ?string $note, User $user): StockCount
    {
        if ($location !== null && $location->warehouse_id !== $warehouse->id) {
            throw new ShopException(__('Lokasyon seçilen depoya ait değil.'));
        }

        return DB::transaction(function () use ($warehouse, $location, $note, $user) {
            Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->first();

            $overlap = StockCount::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('status', StockCountStatus::Open)
                ->when($location, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->whereNull('warehouse_location_id')
                    ->orWhere('warehouse_location_id', $location->id)))
                ->value('number');

            if ($overlap !== null) {
                throw new ShopException(__('Bu kapsamda açık bir sayım var (:overlap). Önce onu onaylayın ya da iptal edin.', ['overlap' => $overlap]));
            }

            return StockCount::query()->create([
                'number' => OrderService::nextNumber('SY', 'stock_counts'),
                'warehouse_id' => $warehouse->id,
                'warehouse_location_id' => $location?->id,
                'status' => StockCountStatus::Open,
                'note' => $note,
                'user_id' => $user->id,
            ]);
        });
    }

    /**
     * Sayılan miktarı kaydeder: `add` ise mevcut sayıma ekler (barkod okutma), değilse eşitler.
     */
    public function record(
        StockCount $count,
        Product $product,
        WarehouseLocation $location,
        ?string $lotCode,
        ?string $expires,
        int $quantity,
        bool $add,
        User $user,
    ): StockCountLine {
        $this->ensureInScope($count, $location);

        $lot = null;

        if ($product->tracksLots()) {
            if (trim((string) $lotCode) === '') {
                throw new ShopException($product->tracksSerials()
                    ? __(':name seri takipli; seri numarası girin.', ['name' => $product->name])
                    : __(':name lot takipli; lot numarası girin.', ['name' => $product->name]));
            }

            $lot = StockLot::resolve($product, (string) $lotCode, $product->tracksExpiry() ? $expires : null);
        }

        return DB::transaction(function () use ($count, $product, $location, $lot, $quantity, $add, $user) {
            $this->lockOpen($count);

            /** @var StockCountLine|null $line */
            $line = $count->lines()
                ->where('product_id', $product->id)
                ->where('warehouse_location_id', $location->id)
                ->when($lot, fn (Builder $q) => $q->where('stock_lot_id', $lot->id), fn (Builder $q) => $q->whereNull('stock_lot_id'))
                ->lockForUpdate()
                ->first();

            $counted = $add ? ($line?->counted ?? 0) + $quantity : $quantity;

            if ($product->tracksSerials() && $counted > 1) {
                throw new ShopException(__(':code seri numarası zaten sayıldı.', ['code' => $lot?->code]));
            }

            $attributes = [
                'counted' => max(0, $counted),
                'expected' => $this->systemQuantity($product, $location, $lot),
                'user_id' => $user->id,
            ];

            if ($line === null) {
                $line = $count->lines()->create([
                    'product_id' => $product->id,
                    'warehouse_location_id' => $location->id,
                    'stock_lot_id' => $lot?->id,
                    ...$attributes,
                ]);
            } else {
                $line->update($attributes);
            }

            return $line;
        });
    }

    public function updateLine(StockCountLine $line, int $counted, User $user): void
    {
        DB::transaction(function () use ($line, $counted, $user) {
            $count = $line->stockCount;
            $this->lockOpen($count);

            $product = $line->product;

            if ($product->tracksSerials() && $counted > 1) {
                throw new ShopException(__('Seri numaralı satırda miktar en fazla 1 olabilir.'));
            }

            $line->update([
                'counted' => max(0, $counted),
                'expected' => $this->systemQuantity($product, $line->location, $line->lot),
                'user_id' => $user->id,
            ]);
        });
    }

    public function removeLine(StockCountLine $line): void
    {
        DB::transaction(function () use ($line) {
            $this->lockOpen($line->stockCount);
            $line->delete();
        });
    }

    /**
     * Farkları toplu uygular ve oturumu kapatır.
     */
    public function approve(StockCount $count, bool $zeroUncounted, User $user): StockCount
    {
        DB::transaction(function () use ($count, $zeroUncounted, $user) {
            $this->lockOpen($count);

            $warehouse = $count->warehouse;
            $note = __('Sayım :number', ['number' => $count->number]);
            $lines = $count->lines()->with(['product', 'location', 'lot'])->get();

            foreach ($lines as $line) {
                $product = $line->product;

                if ($product === null || $product->trashed() || $line->location === null) {
                    continue;
                }

                $delta = $line->difference();

                // Lotlu satırda sayımdan sonra çıkış olduysa lot eksiye düşürülemez.
                if ($delta < 0 && $line->lot !== null) {
                    $delta = max($delta, -$this->systemQuantity($product, $line->location, $line->lot));
                }

                if ($delta !== 0) {
                    $this->stock->change($product, $warehouse, $delta, StockMovementType::Adjust, $note, $user, $count, false, $line->location, $line->lot);
                }
            }

            if ($zeroUncounted) {
                foreach ($this->uncounted($count, $lines) as $row) {
                    $this->stock->change($row->product, $warehouse, -$row->quantity, StockMovementType::Adjust, __(':note (sayılmadı)', ['note' => $note]), $user, $count, false, $row->location, $row->lot);
                }
            }

            $count->update([
                'status' => StockCountStatus::Completed,
                'zero_uncounted' => $zeroUncounted,
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
        });

        StockCountClosedEvent::dispatch($count->refresh(), $user);

        return $count;
    }

    public function cancel(StockCount $count, User $user): StockCount
    {
        DB::transaction(function () use ($count, $user) {
            $this->lockOpen($count);

            $count->update([
                'status' => StockCountStatus::Cancelled,
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
        });

        StockCountClosedEvent::dispatch($count->refresh(), $user);

        return $count;
    }

    /**
     * Kapsamda olup sayılmamış, miktarı sıfırdan farklı stok satırları.
     *
     * @param  Collection<int, StockCountLine>|null  $lines
     * @return Collection<int, LocationStock>
     */
    public function uncounted(StockCount $count, ?Collection $lines = null): Collection
    {
        $lines ??= $count->lines()->with('product')->get();

        $counted = $lines->mapWithKeys(fn (StockCountLine $l) => [
            $this->key($l->product, $l->warehouse_location_id, $l->stock_lot_id) => true,
        ]);

        return LocationStock::query()
            ->where('warehouse_id', $count->warehouse_id)
            ->when($count->warehouse_location_id, fn (Builder $q, string $id) => $q->where('warehouse_location_id', $id))
            ->where('quantity', '!=', 0)
            ->whereHas('product')
            ->with(['product', 'location', 'lot'])
            ->get()
            ->reject(fn (LocationStock $s) => $s->location === null
                || isset($counted[$this->key($s->product, $s->warehouse_location_id, $s->stock_lot_id)]))
            ->values();
    }

    /**
     * Sistem miktarı. Lot takipsiz üründe lokasyondaki tüm satırlar (lotlu
     * olanlar dahil: izlenebilirlik sonradan kapatılmış olabilir) toplanır.
     */
    public function systemQuantity(Product $product, WarehouseLocation $location, ?StockLot $lot): int
    {
        return (int) LocationStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_location_id', $location->id)
            ->when($product->tracksLots(), fn (Builder $q) => $lot !== null
                ? $q->where('stock_lot_id', $lot->id)
                : $q->whereNull('stock_lot_id'))
            ->sum('quantity');
    }

    private function key(?Product $product, string $locationId, ?string $lotId): string
    {
        return $product?->tracksLots()
            ? "{$product->id}|{$locationId}|{$lotId}"
            : "{$product?->id}|{$locationId}";
    }

    private function ensureInScope(StockCount $count, WarehouseLocation $location): void
    {
        if ($location->warehouse_id !== $count->warehouse_id) {
            throw new ShopException(__('Lokasyon sayılan depoya ait değil.'));
        }

        if ($count->warehouse_location_id !== null && $count->warehouse_location_id !== $location->id) {
            throw new ShopException(__('Bu sayım yalnız seçilen lokasyonu kapsıyor.'));
        }
    }

    private function lockOpen(StockCount $count): void
    {
        $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isOpen()) {
            throw new ShopException(__(':number sayımı kapanmış; değişiklik yapılamaz.', ['number' => $count->number]));
        }
    }
}
