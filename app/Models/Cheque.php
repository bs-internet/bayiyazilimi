<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Concerns\HasMedia;
use App\Enums\Shop\ChequeStatus;
use App\Enums\Shop\CollectionType;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Müşteriden alınan çek / senet. Alındığında tahsilat kaydı
 * (`payment`) ve cari alacak yazılır; karşılıksız ya da iade olursa cariye
 * aynı tutarda borç (cheque_bounce). Görsel `image` koleksiyonunda.
 *
 * @property BigDecimal $amount
 * @property ChequeStatus $status
 * @property CollectionType $type
 */
#[Fillable([
    'account_id',
    'payment_id',
    'type',
    'serial_no',
    'bank_name',
    'branch',
    'drawer',
    'amount',
    'currency_code',
    'due_date',
    'status',
    'endorsed_to',
    'notes',
    'user_id',
    'status_changed_at',
])]
class Cheque extends Model
{
    use BelongsToAccount, HasMedia, HasUuids;

    public const IMAGE = 'image';

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'amount' => Decimal::class.':2',
            'due_date' => 'immutable_date',
            'status' => ChequeStatus::class,
            'status_changed_at' => 'immutable_datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function mediaAccountCode(): ?string
    {
        return Account::query()->whereKey($this->account_id)->value('code');
    }
}
