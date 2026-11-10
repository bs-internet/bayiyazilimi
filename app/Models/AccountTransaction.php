<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Cari hareket. Silinmez; yanlış kayıt ters kayıtla (`reversal_of`) düzeltilir,
 * asıl kayıt `reversed_at` ile işaretlenir. Tutar ana para birimindedir.
 *
 * @property BigDecimal $amount
 * @property ?BigDecimal $original_amount
 * @property ?BigDecimal $exchange_rate
 */
#[Fillable([
    'account_id',
    'type',
    'direction',
    'amount',
    'currency_code',
    'original_amount',
    'original_currency',
    'exchange_rate',
    'transaction_date',
    'due_date',
    'source_type',
    'source_id',
    'origin',
    'external_ref',
    'reversal_of',
    'reversed_at',
    'description',
    'user_id',
])]
class AccountTransaction extends Model
{
    use BelongsToAccount, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'amount' => Decimal::class.':2',
            'original_amount' => Decimal::class.':2',
            'exchange_rate' => Decimal::class.':6',
            'transaction_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'reversed_at' => 'immutable_datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reversed(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }

    /** Bakiyeye etkisi (borç +, alacak −). */
    public function signedAmount(): BigDecimal
    {
        return $this->amount->multipliedBy($this->direction->sign());
    }

    public function isReversal(): bool
    {
        return $this->reversal_of !== null;
    }

    /**
     * Dış sistemden (REST API / ERP) belgesiz yazılmış kayıt: tahsilat ya da
     * dekont. Belgesi olmadığı için panelden ters kaydı yapılabilir.
     */
    public function isExternal(): bool
    {
        return $this->origin !== 'system' && $this->source_type === null && $this->reversal_of === null;
    }

    /** Ters kaydı yapılmamış ve kendisi ters kayıt olmayan hareketler. */
    #[Scope]
    protected function effective(Builder $query): void
    {
        $query->whereNull('reversed_at')->whereNull('reversal_of');
    }
}
