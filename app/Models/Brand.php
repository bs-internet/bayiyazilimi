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
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'slug',
    'description',
    'logo',
    'status',
    'sort_order',
])]
class Brand extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return str_starts_with($this->logo, '/') || str_starts_with($this->logo, 'http')
            ? $this->logo
            : Storage::disk('public')->url($this->logo);
    }
}
