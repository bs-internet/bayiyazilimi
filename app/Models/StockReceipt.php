<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Shop\ReceiptDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Mal kabul (tedarikçi irsaliyesi / faturasıyla stok girişi). Satırları stok
 * hareketleridir; kayıt sonradan değiştirilmez (düzeltme stok çıkışıyla yapılır).
 */
#[Fillable([
    'number',
    'supplier_id',
    'document_type',
    'document_no',
    'document_date',
    'warehouse_id',
    'note',
    'user_id',
])]
class StockReceipt extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'document_type' => ReceiptDocumentType::class,
            'document_date' => 'immutable_date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
