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
 * Havale / EFT için banka hesabı (Tanımlamalar → Banka Hesapları). Aktif hesaplar
 * ödeme sayfasında, sipariş ekranında ve sipariş e-postasında listelenir.
 */
#[Fillable([
    'bank_name',
    'branch',
    'account_holder',
    'iban',
    'account_number',
    'currency_code',
    'status',
    'sort_order',
])]
class BankAccount extends Model
{
    use HasSortOrder, HasStatus, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    /** IBAN dörtlü gruplar halinde: "TR12 0006 4000 …". */
    public function formattedIban(): string
    {
        return trim(chunk_split($this->iban, 4, ' '));
    }

    /**
     * Müşteriye gösterilecek aktif hesaplar.
     *
     * @return array<int, array{bank_name: string, branch: ?string, account_holder: string, iban: string, account_number: ?string, currency_code: ?string}>
     */
    public static function forDisplay(): array
    {
        return static::query()->active()->ordered()->get()->map(fn (self $a) => [
            'bank_name' => $a->bank_name,
            'branch' => $a->branch,
            'account_holder' => $a->account_holder,
            'iban' => $a->formattedIban(),
            'account_number' => $a->account_number,
            'currency_code' => $a->currency_code,
        ])->all();
    }
}
