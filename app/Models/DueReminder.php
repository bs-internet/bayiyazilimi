<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir borca (cari hareket) bir aşamada gönderilen son vade hatırlatması
 * (bkz. DueReminderService).
 *
 * @property string $stage upcoming | overdue
 * @property CarbonImmutable $sent_at
 */
#[Fillable(['account_transaction_id', 'stage', 'sent_at'])]
class DueReminder extends Model
{
    use HasUuids;

    public const UPCOMING = 'upcoming';

    public const OVERDUE = 'overdue';

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<AccountTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'account_transaction_id');
    }
}
