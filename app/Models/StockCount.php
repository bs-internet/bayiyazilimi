<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Shop\StockCountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Sayım oturumu. Açıkken satırlar (barkodla) toplanır; onaylanınca farklar
 * "Sayım Düzeltme" hareketi olarak yazılır ve oturum kapanır.
 */
#[Fillable([
    'number',
    'warehouse_id',
    'warehouse_location_id',
    'status',
    'zero_uncounted',
    'note',
    'user_id',
    'closed_by',
    'closed_at',
])]
class StockCount extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'zero_uncounted' => 'boolean',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === StockCountStatus::Open;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Sayım tek lokasyonla sınırlıysa o lokasyon. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
