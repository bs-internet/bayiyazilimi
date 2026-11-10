<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Shop\QuoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teklifin adım adım geçmişi (talep, gönderim, revize isteği, onay …) ve notları.
 */
#[Fillable([
    'quote_id',
    'status',
    'note',
    'by_customer',
    'user_id',
])]
class QuoteHistory extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'by_customer' => 'boolean',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
