<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasStatus;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Depo içi adres: koridor → raf → göz (ör. A-03-2). Her deponun silinemeyen bir
 * "Atanmamış" lokasyonu vardır; yeri belirsiz stok orada durur.
 */
#[Fillable([
    'warehouse_id',
    'code',
    'aisle',
    'rack',
    'bin',
    'is_unassigned',
    'status',
    'sort_order',
])]
class WarehouseLocation extends Model
{
    use HasStatus, HasUuids;

    public const UNASSIGNED_CODE = 'ATANMAMIS';

    protected function casts(): array
    {
        return [
            'is_unassigned' => 'boolean',
            'status' => Status::class,
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(LocationStock::class);
    }

    /**
     * Toplama sırası: koridor/raf/göz koduna göre, "Atanmamış" en sonda.
     *
     * @param  Builder<WarehouseLocation>  $query
     */
    #[Scope]
    protected function pickOrder(Builder $query): void
    {
        $query->orderBy('is_unassigned')->orderBy('sort_order')->orderBy('code');
    }

    public function label(): string
    {
        return $this->is_unassigned ? __('Atanmamış') : $this->code;
    }

    /**
     * Koridor / raf / göz parçalarından lokasyon kodu.
     */
    public static function makeCode(?string $aisle, ?string $rack, ?string $bin): string
    {
        return mb_strtoupper(implode('-', array_filter(
            [trim((string) $aisle), trim((string) $rack), trim((string) $bin)],
            fn (string $part) => $part !== '',
        )));
    }
}
