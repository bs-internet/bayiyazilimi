<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasSortOrder;
use App\Concerns\HasStatus;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'parent_id',
    'name',
    'slug',
    'description',
    'image',
    'meta_title',
    'meta_description',
    'status',
    'sort_order',
])]
class Category extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    public const TREE_CACHE_KEY = 'shop.category-tree';

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::TREE_CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, '/') || str_starts_with($this->image, 'http')
            ? $this->image
            : Storage::disk('public')->url($this->image);
    }

    /**
     * Aktif kategorilerin düz listesi (id, parent_id, name, slug, sort_order) — önbellekli.
     *
     * @return Collection<int, array{id: string, parent_id: ?string, name: string, slug: string, sort_order: int, image: ?string}>
     */
    public static function flatTree(): Collection
    {
        return collect(Cache::rememberForever(self::TREE_CACHE_KEY, fn () => static::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'sort_order', 'image'])
            ->map(fn (self $c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'sort_order' => $c->sort_order,
                'image' => $c->image,
            ])
            ->all()));
    }

    /**
     * Bu kategori ve tüm alt kategorilerinin id'leri.
     *
     * @return array<int, string>
     */
    public function descendantIds(): array
    {
        $all = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];
        $stack = [$this->id];

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($all->get($current, collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Kökten bu kategoriye kadar olan zincir.
     *
     * @return array<int, self>
     */
    public function ancestry(): array
    {
        $chain = [$this];
        $current = $this;
        $guard = 0;

        while ($current->parent_id !== null && $guard++ < 10) {
            // İlişki yerine açık sorgu: preventLazyLoading altında da çalışır.
            $current = static::query()->find($current->parent_id);
            if ($current === null) {
                break;
            }
            array_unshift($chain, $current);
        }

        return $chain;
    }

    public function fullPath(): string
    {
        return collect($this->ancestry())->pluck('name')->implode(' > ');
    }
}
