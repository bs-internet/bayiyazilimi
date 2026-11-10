<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Ürün birimi (Adet, Kutu, Litre …). Ürünler birimi adıyla taşır (`products.unit`).
 */
#[Fillable([
    'name',
    'status',
    'sort_order',
])]
class Unit extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    /**
     * Ürün formu için seçenekler: aktif birimler (+ ürünün mevcut birimi pasif/silinmiş olsa da).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(?string $current = null): array
    {
        $names = static::query()->active()->ordered()->orderBy('name')->pluck('name');

        if ($current !== null && $current !== '' && ! $names->contains($current)) {
            $names->push($current);
        }

        return $names->map(fn (string $n) => ['value' => $n, 'label' => $n])->values()->all();
    }
}
