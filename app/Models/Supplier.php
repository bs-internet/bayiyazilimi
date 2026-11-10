<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasStatus;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'tax_office',
    'tax_number',
    'phone',
    'email',
    'address',
    'note',
    'status',
])]
class Supplier extends Model
{
    use HasStatus, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }
}
