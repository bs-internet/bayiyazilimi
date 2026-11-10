<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Decimal;
use App\Concerns\BelongsToAccount;
use App\Concerns\HasMedia;
use App\Enums\Shop\PaymentNotificationStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Havale / EFT ödeme bildirimi: bayi tutar, tarih, hesap ve
 * dekontla bildirir; yönetici onaylarsa ödeme kaydı + cari alacak yazılır.
 * Dekont `receipt` koleksiyonunda (özel disk).
 *
 * @property BigDecimal $amount
 */
#[Fillable([
    'account_id',
    'user_id',
    'order_id',
    'bank_account_id',
    'amount',
    'currency_code',
    'transfer_date',
    'sender_name',
    'description',
    'status',
    'reject_reason',
    'payment_id',
    'reviewed_by',
    'reviewed_at',
])]
class PaymentNotification extends Model
{
    use BelongsToAccount, HasMedia, HasUuids;

    public const RECEIPT = 'receipt';

    protected function casts(): array
    {
        return [
            'amount' => Decimal::class.':2',
            'transfer_date' => 'immutable_date',
            'status' => PaymentNotificationStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Dekont dosyaları hesabın klasörüne (private/{account.code}) yazılır. */
    protected function mediaAccountCode(): ?string
    {
        return Account::query()->whereKey($this->account_id)->value('code');
    }
}
