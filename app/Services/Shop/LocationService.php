<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Status;
use App\Events\Shop\CatalogChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\LocationStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Support\Facades\DB;

/**
 * Depo lokasyonları (koridor → raf → göz): tekli / toplu oluşturma, düzenleme, silme.
 */
class LocationService
{
    /** Toplu oluşturmada tek seferde açılabilecek en fazla lokasyon. */
    public const BULK_LIMIT = 2000;

    public function create(Warehouse $warehouse, ?string $aisle, ?string $rack, ?string $bin, User $causer): WarehouseLocation
    {
        $code = WarehouseLocation::makeCode($aisle, $rack, $bin);
        $this->ensureCodeFree($warehouse, $code);

        $location = $warehouse->locations()->create([
            'code' => $code,
            'aisle' => $this->part($aisle),
            'rack' => $this->part($rack),
            'bin' => $this->part($bin),
            'status' => Status::Active,
            'sort_order' => $this->nextOrder($warehouse),
        ]);

        CatalogChangedEvent::dispatch($location, 'warehouse', 'updated', __(':name deposuna :code lokasyonunu ekledi.', ['name' => $warehouse->name, 'code' => $code]), $causer);

        return $location;
    }

    /**
     * Koridorlar × raf aralığı × (isteğe bağlı) göz aralığı. Var olan kodlar atlanır.
     * Raf numarası en az iki basamağa, göz son gözün basamağına sıfırla doldurulur
     * (A-01-1 … A-10-4) ki kod sırası toplama sırasıyla aynı olsun.
     *
     * @param  array<int, string>  $aisles
     * @return int oluşturulan lokasyon sayısı
     */
    public function bulkCreate(Warehouse $warehouse, array $aisles, int $rackFrom, int $rackTo, ?int $binFrom, ?int $binTo, User $causer): int
    {
        $aisles = array_values(array_unique(array_filter(array_map(fn ($a) => mb_strtoupper(trim((string) $a)), $aisles))));
        $racks = range($rackFrom, $rackTo);
        $bins = $binFrom !== null && $binTo !== null ? range($binFrom, $binTo) : [null];

        if (count($aisles) * count($racks) * count($bins) > self::BULK_LIMIT) {
            throw new ShopException(__('Tek seferde en fazla :limit lokasyon oluşturulabilir.', ['limit' => self::BULK_LIMIT]));
        }

        $rackPad = max(2, strlen((string) $rackTo));
        $binPad = $binTo !== null ? strlen((string) $binTo) : 0;

        return DB::transaction(function () use ($warehouse, $aisles, $racks, $bins, $rackPad, $binPad, $causer) {
            $existing = $warehouse->locations()->pluck('code')->flip();
            $order = $this->nextOrder($warehouse);
            $created = 0;

            foreach ($aisles as $aisle) {
                foreach ($racks as $rackNo) {
                    foreach ($bins as $binNo) {
                        $rack = str_pad((string) $rackNo, $rackPad, '0', STR_PAD_LEFT);
                        $bin = $binNo === null ? null : str_pad((string) $binNo, $binPad, '0', STR_PAD_LEFT);
                        $code = WarehouseLocation::makeCode($aisle, $rack, $bin);

                        if ($existing->has($code)) {
                            continue;
                        }

                        $warehouse->locations()->create([
                            'code' => $code,
                            'aisle' => $aisle,
                            'rack' => $rack,
                            'bin' => $bin,
                            'status' => Status::Active,
                            'sort_order' => $order++,
                        ]);
                        $existing->put($code, true);
                        $created++;
                    }
                }
            }

            CatalogChangedEvent::dispatch($warehouse, 'warehouse', 'updated', __(':name deposuna :created lokasyon ekledi.', ['name' => $warehouse->name, 'created' => $created]), $causer);

            return $created;
        });
    }

    public function update(WarehouseLocation $location, ?string $aisle, ?string $rack, ?string $bin, User $causer): WarehouseLocation
    {
        if ($location->is_unassigned) {
            throw new ShopException(__('"Atanmamış" lokasyonu değiştirilemez.'));
        }

        $code = WarehouseLocation::makeCode($aisle, $rack, $bin);
        $this->ensureCodeFree($location->warehouse, $code, $location->id);

        $location->update([
            'code' => $code,
            'aisle' => $this->part($aisle),
            'rack' => $this->part($rack),
            'bin' => $this->part($bin),
        ]);

        CatalogChangedEvent::dispatch($location, 'warehouse', 'updated', __(':code lokasyonunu güncelledi.', ['code' => $location->code]), $causer);

        return $location;
    }

    public function toggleStatus(WarehouseLocation $location, User $causer): void
    {
        if ($location->is_unassigned) {
            throw new ShopException(__('"Atanmamış" lokasyonu kapatılamaz.'));
        }

        $location->update(['status' => $location->isActive() ? Status::Passive : Status::Active]);

        CatalogChangedEvent::dispatch($location, 'warehouse', 'status', $location->isActive()
            ? __(':code lokasyonunu aktifleştirdi.', ['code' => $location->code])
            : __(':code lokasyonunu pasifleştirdi.', ['code' => $location->code]), $causer);
    }

    public function delete(WarehouseLocation $location, User $causer): void
    {
        if ($location->is_unassigned) {
            throw new ShopException(__('"Atanmamış" lokasyonu silinemez.'));
        }

        DB::transaction(function () use ($location, $causer) {
            // Denetim ve silme aynı kilit altında: arada gelen stok hareketi silinip
            // depo toplamıyla lokasyonlar ayrışmasın.
            $rows = LocationStock::query()->where('warehouse_location_id', $location->id)->lockForUpdate()->get(['id', 'quantity']);

            if ($rows->contains(fn (LocationStock $s) => (int) $s->quantity !== 0)) {
                throw new ShopException(__(':code lokasyonunda stok var; önce ürünleri başka lokasyona taşıyın.', ['code' => $location->code]));
            }

            LocationStock::query()->where('warehouse_location_id', $location->id)->delete();
            $location->delete();

            CatalogChangedEvent::dispatch(null, 'warehouse', 'updated', __(':code lokasyonunu sildi.', ['code' => $location->code]), $causer);
        });
    }

    private function ensureCodeFree(Warehouse $warehouse, string $code, ?string $ignoreId = null): void
    {
        if ($code === '') {
            throw new ShopException(__('Koridor, raf ya da gözden en az biri girilmelidir.'));
        }

        if ($code === WarehouseLocation::UNASSIGNED_CODE) {
            throw new ShopException(__('Bu kod sisteme ayrılmıştır.'));
        }

        $taken = $warehouse->locations()->where('code', $code)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();

        if ($taken) {
            throw new ShopException(__(':code lokasyonu bu depoda zaten var.', ['code' => $code]));
        }
    }

    private function nextOrder(Warehouse $warehouse): int
    {
        return (int) $warehouse->locations()->max('sort_order') + 1;
    }

    private function part(?string $value): ?string
    {
        $value = mb_strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
