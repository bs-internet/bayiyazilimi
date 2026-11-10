<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir ürünün lotu / partisi ya da seri numarası (ürünün `tracking` değerine
 * göre). Son kullanma tarihi lot üzerinde tutulur; süresi geçmiş lot
 * satılabilir stoğa sayılmaz ve siparişte otomatik seçilmez.
 *
 * @property ?CarbonImmutable $expires_at
 */
#[Fillable([
    'product_id',
    'code',
    'expires_at',
])]
class StockLot extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return HasMany<LocationStock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(LocationStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Koda göre lotu bulur ya da açar; önceden SKT'siz açılmış lota ilk kez SKT
     * verilirse tamamlar. Kod büyük harfe çevrilir.
     */
    public static function resolve(Product $product, string $code, ?string $expires = null): self
    {
        $lot = static::query()->firstOrCreate(
            ['product_id' => $product->id, 'code' => mb_strtoupper(trim($code))],
            ['expires_at' => $expires],
        );

        if ($lot->expires_at === null && $expires !== null) {
            $lot->update(['expires_at' => $expires]);
        }

        return $lot;
    }

    public function isExpired(?CarbonImmutable $today = null): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(($today ?? CarbonImmutable::today()));
    }

    /**
     * Ekranlarda gösterim: "L2311 · SKT 31.12.2026".
     */
    public function label(): string
    {
        return $this->expires_at === null
            ? $this->code
            : __(':code · SKT :this', ['code' => $this->code, 'this' => $this->expires_at->format('d.m.Y')]);
    }
}
