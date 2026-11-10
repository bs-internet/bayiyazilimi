<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'address',
    'phone',
    'is_default',
    'is_sellable',
    'status',
    'sort_order',
])]
class Warehouse extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    protected static function booted(): void
    {
        static::created(fn (Warehouse $warehouse) => $warehouse->unassignedLocation());
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_sellable' => 'boolean',
            'status' => Status::class,
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    /**
     * Yeri belirsiz stoğun tutulduğu lokasyon (her depoda bir tane, gerekirse açılır).
     */
    public function unassignedLocation(): WarehouseLocation
    {
        return $this->locations()->firstOrCreate(
            ['is_unassigned' => true],
            ['code' => WarehouseLocation::UNASSIGNED_CODE, 'status' => Status::Active, 'sort_order' => 0],
        );
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->active()->ordered()->first();
    }
}
