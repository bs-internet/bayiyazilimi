<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\StockMovementType;
use App\Events\Shop\StockAdjustedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\LocationStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Depo, lokasyon ve lot bazlı stok yönetimi. Tüm stok değişiklikleri buradan
 * geçer ve `stock_movements` tablosuna hareket olarak yazılır.
 *
 * Katmanlar: `location_stocks` (ürün × lokasyon × lot; lot takipsiz üründe lot
 * null) → `product_stocks` (depo toplamı) → `products.stock` (satılabilir ve
 * aktif depoların toplamı, süresi geçmiş lotlar hariç).
 *
 * Aynı ürün + depo için her değişiklik önce `product_stocks` satırını kilitler;
 * eşzamanlı işlemler bu kilitle sıralanır.
 */
class StockService
{
    /**
     * Bir depodaki stoğu `delta` kadar değiştirir.
     *
     * Lokasyon / lot verilmezse otomatik seçilir:
     * - giriş: ürünün o depoda en çok bulunduğu lokasyon (yoksa "Atanmamış");
     *   lot takipli üründe lot verilmezse "lot belirsiz" (null) satıra girer.
     * - çıkış: lot takipli üründe SKT'si en yakın, süresi geçmemiş lot önce
     *   (FEFO), sonra toplama sırası; takipsiz üründe toplama sırası. Yetmeyen
     *   kısım "Atanmamış"ta (lot belirsiz) eksiye düşer.
     * - lot verilmiş çıkışta o lotta yeterli stok yoksa ShopException.
     *
     * Her lokasyon/lot parçası için ayrı hareket yazılır; son hareket döner.
     */
    public function change(
        Product $product,
        Warehouse $warehouse,
        int $delta,
        StockMovementType $type,
        ?string $note = null,
        ?User $user = null,
        ?Model $reference = null,
        bool $dispatch = true,
        ?WarehouseLocation $location = null,
        ?StockLot $lot = null,
    ): ?StockMovement {
        $movements = $this->apply($product, $warehouse, $delta, $type, $note, $user, $reference, $dispatch, $location, $lot);

        return $movements === [] ? null : end($movements);
    }

    /**
     * change() ile aynı; yazılan tüm hareketleri döner.
     *
     * @return array<int, StockMovement>
     */
    private function apply(
        Product $product,
        Warehouse $warehouse,
        int $delta,
        StockMovementType $type,
        ?string $note,
        ?User $user,
        ?Model $reference,
        bool $dispatch,
        ?WarehouseLocation $location,
        ?StockLot $lot,
    ): array {
        if ($location !== null && $location->warehouse_id !== $warehouse->id) {
            throw new ShopException(__('Lokasyon seçilen depoya ait değil.'));
        }

        if ($lot !== null && $lot->product_id !== $product->id) {
            throw new ShopException(__('Lot bu ürüne ait değil.'));
        }

        return DB::transaction(function () use ($product, $warehouse, $delta, $type, $note, $user, $reference, $dispatch, $location, $lot) {
            $this->lockProduct($product);
            $stock = $this->lockedStock($product, $warehouse);

            $movements = [];

            foreach ($this->allocate($product, $warehouse, $delta, $location, $lot) as [$target, $targetLot, $part]) {
                $this->changeLocation($product, $target, $targetLot, $part);

                $stock->quantity += $part;
                $stock->save();

                $movement = StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'warehouse_location_id' => $target->id,
                    'stock_lot_id' => $targetLot?->id,
                    'type' => $type,
                    'quantity' => $part,
                    'balance_after' => $stock->quantity,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'note' => $note,
                    'user_id' => $user?->id,
                ]);

                if ($dispatch && $user !== null) {
                    StockAdjustedEvent::dispatch($movement, $user);
                }

                $movement->setRelation('lot', $targetLot);
                $movements[] = $movement;
            }

            if ($product->tracksSerials()) {
                foreach (array_filter(array_map(fn (StockMovement $m) => $m->lot, $movements)) as $serial) {
                    $this->ensureSerialBounds($serial);
                }
            }

            $product->syncStockTotal();

            // Lotlu giriş, aynı depodaki "lot belirsiz" eksi stoğu (ön sipariş vb.) kapatır.
            if ($delta > 0 && $lot !== null && $type !== StockMovementType::LotChange && $product->tracksLots()) {
                $this->settleUnassigned($product, $warehouse, $lot, $user);
            }

            return $movements;
        });
    }

    /**
     * Stok yetmeden düşülen (ön sipariş, eksik sayım) kısım "lot belirsiz" olarak
     * eksiye düşer. Aynı depoya lotlu stok girince bu eksi otomatik kapatılır:
     *
     * 1. Önce eksik düşülen siparişler (en eskiden) yeni lota bağlanır — siparişin
     *    toplama bilgisi ve izlenebilirliği doğru lotu gösterir.
     * 2. Kalan lot belirsiz eksi (sipariş dışı çıkışlar) yeni lottan kapatılır.
     *
     * Her ikisi de "Lot Değişimi" hareketi yazar; depo toplamı değişmez.
     */
    private function settleUnassigned(Product $product, Warehouse $warehouse, StockLot $lot, ?User $user): void
    {
        if ($lot->isExpired()) {
            return;
        }

        $available = fn (): int => (int) LocationStock::query()
            ->where('stock_lot_id', $lot->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->sum('quantity');

        // 1) Eksik düşülmüş siparişler.
        $orderIds = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('stock_lot_id')
            ->where('type', StockMovementType::Sale->value)
            ->where('reference_type', (new Order)->getMorphClass())
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('reference_id')
            ->unique();

        foreach (Order::query()->whereKey($orderIds)->where('stock_deducted', true)->get()->sortBy(fn (Order $o) => $orderIds->search($o->id)) as $order) {
            foreach ($this->orderPicks($order) as $pick) {
                $free = $available();

                if ($free <= 0) {
                    return;
                }

                if ($pick['product_id'] !== $product->id || $pick['lot'] !== null || $pick['warehouse']?->id !== $warehouse->id || $pick['quantity'] <= 0) {
                    continue;
                }

                $this->changeOrderLot($order, $product, $warehouse, $pick['location'], null, $lot, min($free, $pick['quantity']), $user);
            }
        }

        // 2) Sipariş dışı lot belirsiz eksi.
        $negatives = LocationStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('stock_lot_id')
            ->where('quantity', '<', 0)
            ->with('location')
            ->get();

        foreach ($negatives as $row) {
            $take = min($available(), -$row->quantity);

            if ($take <= 0 || $row->location === null) {
                continue;
            }

            $note = __('Lot belirsiz eksi stok :code ile kapatıldı', ['code' => $lot->code]);
            $this->apply($product, $warehouse, -$take, StockMovementType::LotChange, $note, $user, null, false, null, $lot);
            $this->apply($product, $warehouse, $take, StockMovementType::LotChange, $note, $user, null, false, $row->location, null);
        }
    }

    /**
     * Sayım: depodaki miktarı verilen değere eşitler. Lokasyon verilirse yalnız o
     * lokasyon, lot verilirse yalnız o lot (lokasyonla birlikte: o lokasyondaki lot).
     */
    public function setQuantity(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        ?string $note = null,
        ?User $user = null,
        StockMovementType $type = StockMovementType::Adjust,
        ?WarehouseLocation $location = null,
        ?StockLot $lot = null,
    ): ?StockMovement {
        // Mevcut miktar kilit altında okunur: eşzamanlı bir hareket farkı bozmasın.
        return DB::transaction(function () use ($product, $warehouse, $quantity, $note, $user, $type, $location, $lot): ?StockMovement {
            $this->lockProduct($product);
            $stock = $this->lockedStock($product, $warehouse);

            $current = $lot !== null || $location !== null
                ? (int) LocationStock::query()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->when($location, fn (Builder $q) => $q->where('warehouse_location_id', $location->id))
                    ->when($lot, fn (Builder $q) => $q->where('stock_lot_id', $lot->id))
                    ->sum('quantity')
                : (int) $stock->quantity;

            $delta = $quantity - $current;

            if ($delta === 0) {
                return null;
            }

            return $this->change($product, $warehouse, $delta, $type, $note, $user, null, true, $location, $lot);
        });
    }

    /**
     * Elle çıkış: eldeki miktarı aşamaz (eksi stok yalnız sipariş / ön sipariş ile
     * oluşur). Lot verilmezse süresi geçmiş lotlar sayılmaz (otomatik seçim onları
     * almaz; aşan kısım "lot belirsiz" eksiye düşüp süresi geçmiş stoğu satılabilir
     * gösterirdi). Denetim ve düşüm aynı kilit altında yapılır.
     */
    public function issue(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        ?string $note = null,
        ?User $user = null,
        ?WarehouseLocation $location = null,
        ?StockLot $lot = null,
    ): ?StockMovement {
        return DB::transaction(function () use ($product, $warehouse, $quantity, $note, $user, $location, $lot): ?StockMovement {
            $this->lockProduct($product);
            $this->lockedStock($product, $warehouse);

            $available = $this->onHand($product, $warehouse, $location, $lot, sellableOnly: $lot === null);

            if ($quantity > $available) {
                throw new ShopException(__('Yeterli stok yok: en fazla :quantity adet çıkış yapılabilir.', ['quantity' => max(0, $available)]));
            }

            return $this->change($product, $warehouse, -$quantity, StockMovementType::Out, $note, $user, null, true, $location, $lot);
        });
    }

    public function transfer(
        Product $product,
        Warehouse $from,
        Warehouse $to,
        int $quantity,
        ?string $note = null,
        ?User $user = null,
        ?WarehouseLocation $toLocation = null,
        ?WarehouseLocation $fromLocation = null,
        ?StockLot $lot = null,
    ): void {
        if ($from->is($to)) {
            throw new ShopException(__('Kaynak ve hedef depo aynı olamaz.'));
        }

        if ($quantity <= 0) {
            throw new ShopException(__('Transfer miktarı sıfırdan büyük olmalıdır.'));
        }

        if ($fromLocation !== null && $fromLocation->warehouse_id !== $from->id) {
            throw new ShopException(__('Kaynak lokasyon kaynak depoya ait değil.'));
        }

        if ($toLocation !== null && $toLocation->warehouse_id !== $to->id) {
            throw new ShopException(__('Hedef lokasyon hedef depoya ait değil.'));
        }

        DB::transaction(function () use ($product, $from, $to, $quantity, $note, $user, $toLocation, $fromLocation, $lot) {
            // Önce ürün (sabit sıra): ters yönlü eşzamanlı iki transfer depo
            // satırlarını çapraz kilitleyip kilitlenmez (deadlock).
            $this->lockProduct($product);
            $this->lockedStock($product, $from);

            // Lot verilmezse süresi geçmiş lotlar sayılmaz: otomatik seçim onları
            // almaz ve aşan kısım hedefte satılabilir stok olurdu.
            $available = $this->onHand($product, $from, null, $lot, sellableOnly: $lot === null);

            if ($available < $quantity) {
                throw new ShopException(__(':name deposunda yeterli stok yok (mevcut: :available).', ['name' => $from->name, 'available' => $available]));
            }

            // Kaynak lokasyon seçildiyse yalnız oradan düşer; seçilmezse otomatik.
            if ($fromLocation !== null) {
                $atLocation = $this->onHand($product, $from, $fromLocation, $lot, sellableOnly: $lot === null);

                if ($atLocation < $quantity) {
                    throw new ShopException(__(':from_location lokasyonunda yeterli stok yok (mevcut: :at_location).', ['from_location' => $fromLocation->label(), 'at_location' => $atLocation]));
                }
            }

            $out = collect($this->apply($product, $from, -$quantity, StockMovementType::TransferOut, $note ?? "→ {$to->name}", $user, null, true, $fromLocation, $lot));

            // Lotlar hedefe aynen taşınır (lot belirsiz kısım belirsiz kalır).
            foreach ($out->groupBy(fn (StockMovement $m) => (string) $m->stock_lot_id) as $parts) {
                $this->change($product, $to, (int) -$parts->sum('quantity'), StockMovementType::TransferIn, $note ?? __('← :name', ['name' => $from->name]), $user, null, true, $toLocation, $parts->first()->lot);
            }
        });
    }

    /**
     * Aynı depoda lokasyon değişimi: depo toplamı değişmez, iki hareket yazılır.
     * Lot takipli üründe lot verilmelidir (yalnız o lot taşınır).
     */
    public function relocate(Product $product, WarehouseLocation $from, WarehouseLocation $to, int $quantity, ?string $note = null, ?User $user = null, ?StockLot $lot = null): void
    {
        if ($from->warehouse_id !== $to->warehouse_id) {
            throw new ShopException(__('Lokasyonlar aynı depoda olmalıdır; depolar arası için transfer kullanın.'));
        }

        if ($from->is($to)) {
            throw new ShopException(__('Kaynak ve hedef lokasyon aynı olamaz.'));
        }

        if ($quantity <= 0) {
            throw new ShopException(__('Miktar sıfırdan büyük olmalıdır.'));
        }

        if ($product->tracksLots() && $lot === null) {
            throw new ShopException(__('Lot / seri takipli üründe taşınacak lot seçilmelidir.'));
        }

        DB::transaction(function () use ($product, $from, $to, $quantity, $note, $user, $lot) {
            $balance = (int) ProductStock::query()
                ->lockForUpdate()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $from->warehouse_id)
                ->value('quantity');

            $available = (int) LocationStock::query()
                ->lockForUpdate()
                ->where('product_id', $product->id)
                ->where('warehouse_location_id', $from->id)
                ->where('stock_lot_id', $lot?->id)
                ->value('quantity');

            if ($available < $quantity) {
                throw new ShopException(__(':from lokasyonunda yeterli stok yok (mevcut: :available).', ['from' => $from->label(), 'available' => $available]));
            }

            foreach ([[$from, -$quantity, __('→ :to', ['to' => $to->label()])], [$to, $quantity, __('← :from', ['from' => $from->label()])]] as [$location, $part, $defaultNote]) {
                $this->changeLocation($product, $location, $lot, $part);

                $movement = StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $location->warehouse_id,
                    'warehouse_location_id' => $location->id,
                    'stock_lot_id' => $lot?->id,
                    'type' => StockMovementType::Relocate,
                    'quantity' => $part,
                    'balance_after' => $balance,
                    'note' => $note ?? $defaultNote,
                    'user_id' => $user?->id,
                ]);

                if ($user !== null) {
                    StockAdjustedEvent::dispatch($movement, $user);
                }
            }
        });
    }

    /**
     * Siparişte bir toplama parçasının lotunu / seri numarasını değiştirir: eski
     * lot aynı lokasyona geri eklenir, yeni lot aynı depodan düşülür. Sipariş ve
     * depo toplamı değişmez; iki "Lot Değişimi" hareketi yazılır.
     */
    public function changeOrderLot(
        Order $order,
        Product $product,
        Warehouse $warehouse,
        ?WarehouseLocation $location,
        ?StockLot $from,
        StockLot $to,
        int $quantity,
        ?User $user = null,
    ): void {
        if (! $order->stock_deducted) {
            throw new ShopException(__('Bu siparişte düşülmüş stok yok.'));
        }

        if ($quantity <= 0) {
            throw new ShopException(__('Miktar sıfırdan büyük olmalıdır.'));
        }

        if ($from !== null && $from->is($to)) {
            throw new ShopException(__('Yeni lot mevcut lotla aynı.'));
        }

        if ($to->isExpired()) {
            throw new ShopException(__(':code lotunun son kullanma tarihi geçmiş.', ['code' => $to->code]));
        }

        DB::transaction(function () use ($order, $product, $warehouse, $location, $from, $to, $quantity, $user) {
            $picked = (int) $this->orderPicks($order)
                ->filter(fn (array $p) => $p['product_id'] === $product->id
                    && $p['warehouse']?->id === $warehouse->id
                    && $p['location']?->id === $location?->id
                    && $p['lot']?->id === $from?->id)
                ->sum('quantity');

            if ($picked < $quantity) {
                throw new ShopException(__('Siparişte bu lokasyon/lottan düşülen miktar :picked.', ['picked' => $picked]));
            }

            $note = __('Sipariş :number: :from → :to', ['number' => $order->number, 'from' => $from?->code ?? __('lot belirsiz'), 'to' => $to->code]);

            $this->change($product, $warehouse, -$quantity, StockMovementType::LotChange, $note, $user, $order, false, null, $to);
            $this->change($product, $warehouse, $quantity, StockMovementType::LotChange, $note, $user, $order, false, $location, $from);
        });
    }

    /**
     * Siparişin üzerinde kalan net düşümleri: satış, lot değişimi ve iptal/iade
     * (sipariş referanslı) hareketlerinin depo × lokasyon × lot bazında toplamı.
     * Miktar pozitif = siparişe düşülmüş adet. Düşüm sırasıyla döner.
     *
     * @return Collection<int, array{product_id: string, product: ?Product, warehouse: ?Warehouse, location: ?WarehouseLocation, lot: ?StockLot, quantity: int}>
     */
    public function orderPicks(Order $order): Collection
    {
        return StockMovement::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->whereIn('type', [StockMovementType::Sale->value, StockMovementType::LotChange->value, StockMovementType::Return->value])
            ->with(['product', 'warehouse', 'location', 'lot'])
            ->orderBy('created_at')
            ->orderBy('id') // UUIDv7: aynı saniyedeki hareketlerde oluşma sırası
            ->get()
            ->groupBy(fn (StockMovement $m) => "{$m->product_id}|{$m->warehouse_id}|{$m->warehouse_location_id}|{$m->stock_lot_id}")
            ->map(fn (Collection $group) => [
                'product_id' => $group->first()->product_id,
                'product' => $group->first()->product,
                'warehouse' => $group->first()->warehouse,
                'location' => $group->first()->location,
                'lot' => $group->first()->lot,
                'quantity' => (int) -$group->sum('quantity'),
            ])
            ->filter(fn (array $pick) => $pick['quantity'] !== 0)
            ->values();
    }

    /**
     * sellableIn()'in toplu hali: ürün id => o depodaki satılabilir miktar.
     *
     * @param  array<int, string>  $productIds
     * @return array<string, int>
     */
    public function sellableMap(array $productIds, Warehouse $warehouse): array
    {
        if ($productIds === []) {
            return [];
        }

        $totals = ProductStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id');

        $expired = ShopSettings::traceability()
            ? LocationStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereIn('product_id', $productIds)
                ->where('quantity', '>', 0)
                ->whereHas('lot', fn (Builder $q) => $q->whereDate('expires_at', '<', CarbonImmutable::today()))
                ->groupBy('product_id')
                ->selectRaw('product_id, sum(quantity) as qty')
                ->pluck('qty', 'product_id')
            : collect();

        return collect($productIds)
            ->mapWithKeys(fn (string $id) => [$id => max(0, (int) ($totals[$id] ?? 0) - (int) ($expired[$id] ?? 0))])
            ->all();
    }

    /**
     * Depoda (verilirse yalnız o lokasyon / lotta) fiilen bulunan miktar; eksi
     * satırlar hariç. `sellableOnly`: izlenebilirlik açıkken süresi geçmiş lotlar
     * da hariç (otomatik seçimin alabileceği miktar).
     */
    public function onHand(Product $product, Warehouse $warehouse, ?WarehouseLocation $location = null, ?StockLot $lot = null, bool $sellableOnly = false): int
    {
        return (int) LocationStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->when($location, fn (Builder $q) => $q->where('warehouse_location_id', $location->id))
            ->when($lot, fn (Builder $q) => $q->where('stock_lot_id', $lot->id))
            ->when($sellableOnly && ShopSettings::traceability(), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereNull('stock_lot_id')
                ->orWhereDoesntHave('lot', fn (Builder $l) => $l->whereDate('expires_at', '<', CarbonImmutable::today()))))
            ->sum('quantity');
    }

    /**
     * Bir depoda satılabilir (süresi geçmemiş) miktar.
     */
    public function sellableIn(Product $product, Warehouse $warehouse): int
    {
        $total = (int) ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity');

        if (! ShopSettings::traceability()) {
            return $total;
        }

        $expired = (int) LocationStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->whereHas('lot', fn (Builder $q) => $q->whereDate('expires_at', '<', CarbonImmutable::today()))
            ->sum('quantity');

        return $total - $expired;
    }

    /**
     * @return array<int, array{0: WarehouseLocation, 1: ?StockLot, 2: int}>
     */
    private function allocate(Product $product, Warehouse $warehouse, int $delta, ?WarehouseLocation $location, ?StockLot $lot): array
    {
        if ($delta === 0) {
            return [];
        }

        if ($delta > 0) {
            return [[$location ?? $this->mainLocation($product, $warehouse), $lot, $delta]];
        }

        // Lot / lokasyon belirtilmemiş, takipsiz üründe lokasyon verilmiş çıkış:
        // eski davranış (o lokasyondan, gerekirse eksiye).
        if ($location !== null && $lot === null && ! $product->tracksLots()) {
            return [[$location, null, $delta]];
        }

        $today = CarbonImmutable::today();
        $skipExpired = $lot === null && ShopSettings::traceability();

        $rows = LocationStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->when($location, fn (Builder $q) => $q->where('warehouse_location_id', $location->id))
            ->when($lot, fn (Builder $q) => $q->where('stock_lot_id', $lot->id))
            ->with(['location', 'lot'])
            ->get()
            ->filter(fn (LocationStock $s) => $s->location !== null)
            // Otomatik seçimde pasif lokasyondan alınmaz (lokasyon açıkça verilirse alınır).
            ->reject(fn (LocationStock $s) => $location === null && ! $s->location->is_unassigned && ! $s->location->isActive())
            // Otomatik seçimde süresi geçmiş lot satılmaz (lot açıkça verilirse alınır).
            ->reject(fn (LocationStock $s) => $skipExpired && $s->lot?->isExpired($today))
            ->sortBy(fn (LocationStock $s) => $this->pickKey($s))
            ->values();

        $remaining = -$delta;
        $parts = [];

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $rowLocation = $row->location;

            if ($rowLocation === null) {
                continue;
            }

            $take = min($row->quantity, $remaining);
            $parts[] = [$rowLocation, $row->lot, -$take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            if ($lot !== null) {
                throw new ShopException(__(':code lotunda yeterli stok yok.', ['code' => $lot->code]));
            }

            // Yetmeyen kısım (ön sipariş, eksik sayım) lot belirsiz olarak eksiye düşer.
            $parts[] = [$location ?? $warehouse->unassignedLocation(), null, -$remaining];
        }

        return $parts;
    }

    /**
     * Çıkış sırası: lotlu satırlar SKT'ye göre (SKT'siz en sonda, eşitse eski lot
     * önce), sonra lot belirsiz satırlar; aynı lot içinde toplama sırası
     * (koridor/raf/göz, Atanmamış en sonda).
     */
    private function pickKey(LocationStock $stock): string
    {
        $lot = $stock->lot;
        $loc = $stock->location;

        return implode('|', [
            $lot === null ? 1 : 0,
            $lot?->expires_at?->format('Ymd') ?? '99999999',
            $lot?->created_at?->format('YmdHis') ?? '',
            (string) $lot?->id,
            $loc->is_unassigned ? 1 : 0,
            sprintf('%010d', $loc->sort_order),
            $loc->code,
        ]);
    }

    /**
     * Girişte varsayılan lokasyon: ürünün bu depoda en çok bulunduğu lokasyon (yoksa Atanmamış).
     */
    private function mainLocation(Product $product, Warehouse $warehouse): WarehouseLocation
    {
        $main = LocationStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->with('location')
            ->get()
            ->filter(fn (LocationStock $s) => $s->location !== null && ! $s->location->is_unassigned && $s->location->isActive())
            ->groupBy('warehouse_location_id')
            ->map(fn (Collection $rows) => ['location' => $rows->first()->location, 'quantity' => (int) $rows->sum('quantity')])
            ->filter(fn (array $r) => $r['quantity'] > 0)
            ->sortByDesc('quantity')
            ->first();

        return $main['location'] ?? $warehouse->unassignedLocation();
    }

    /**
     * Transaction içinde: ürün satırını kilitler. Aynı ürünün tüm stok hareketleri
     * (farklı depolarda da) sıraya girer: depo toplamı (`products.stock`) ve
     * kritik stok bayrağı eşzamanlı hareketlerde kaymaz. Güncel stok / bayrak
     * değerleri verilen modele aktarılır.
     */
    private function lockProduct(Product $product): void
    {
        $locked = Product::query()->withTrashed()->lockForUpdate()->find($product->id, ['id', 'stock', 'low_stock_notified_at']);

        if ($locked === null) {
            return;
        }

        foreach (['stock', 'low_stock_notified_at'] as $key) {
            $product->setAttribute($key, $locked->getAttribute($key));
            $product->syncOriginalAttribute($key);
        }
    }

    /**
     * Transaction içinde: ürünün depo satırını (yoksa oluşturup) kilitler. Satır
     * yokken `firstOrCreate` + kilit hiçbir şeyi kilitlemez; önce boş satır eklenir.
     */
    private function lockedStock(Product $product, Warehouse $warehouse): ProductStock
    {
        ProductStock::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProductStock::query()
            ->lockForUpdate()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
    }

    private function changeLocation(Product $product, WarehouseLocation $location, ?StockLot $lot, int $delta): void
    {
        /** @var LocationStock $stock */
        $stock = LocationStock::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_location_id' => $location->id, 'stock_lot_id' => $lot?->id],
                ['warehouse_id' => $location->warehouse_id, 'quantity' => 0],
            );

        $stock->quantity += $delta;
        $stock->save();
    }

    /**
     * Seri numarası tüm depolarda en fazla 1 adet olabilir ve eksiye düşemez.
     */
    private function ensureSerialBounds(StockLot $lot): void
    {
        $total = (int) LocationStock::query()->where('stock_lot_id', $lot->id)->sum('quantity');

        if ($total > 1) {
            throw new ShopException(__(':code seri numarası zaten stokta.', ['code' => $lot->code]));
        }

        if ($total < 0) {
            throw new ShopException(__(':code seri numarası stokta değil.', ['code' => $lot->code]));
        }
    }
}
