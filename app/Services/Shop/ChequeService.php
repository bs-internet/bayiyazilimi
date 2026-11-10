<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\ChequeStatus;
use App\Enums\Shop\CollectionType;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Events\Shop\ChequeStatusChangedEvent;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\Cheque;
use App\Models\Payment;
use App\Models\User;
use App\Services\Support\MediaService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Çek / senet portföyü (Cari Ayarları'ndan açılır).
 */
class ChequeService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly MediaService $media,
    ) {}

    /**
     * Tahsilattan çek / senet kaydı (CollectionService::collect çağırır).
     *
     * @param  array{serial_no: string, bank_name?: ?string, branch?: ?string, drawer?: ?string, due_date: string, description?: ?string}  $data
     */
    public function createForPayment(Payment $payment, CollectionType $type, User $causer, array $data, ?UploadedFile $image = null): Cheque
    {
        $cheque = Cheque::query()->withoutGlobalScopes()->create([
            'account_id' => $payment->account_id,
            'payment_id' => $payment->id,
            'type' => $type,
            'serial_no' => $data['serial_no'],
            'bank_name' => $data['bank_name'] ?? null,
            'branch' => $data['branch'] ?? null,
            'drawer' => $data['drawer'] ?? null,
            'amount' => $payment->amount,
            'currency_code' => $payment->currency_code,
            'due_date' => $data['due_date'],
            'status' => ChequeStatus::Portfolio,
            'notes' => $data['description'] ?? null,
            'user_id' => $causer->id,
            'status_changed_at' => now(),
        ]);

        if ($image !== null) {
            $this->media->attach($cheque, $image, Cheque::IMAGE);
        }

        return $cheque;
    }

    /**
     * Durum değişikliği. Karşılıksız / iade: cariye aynı tutarda borç ve
     * tahsilat kaydı "başarısız"; ciro edilende kime verildiği zorunludur.
     */
    public function changeStatus(Cheque $cheque, ChequeStatus $status, User $causer, ?string $endorsedTo = null, ?string $note = null): void
    {
        if ($status === ChequeStatus::Endorsed && trim((string) $endorsedTo) === '') {
            throw new ShopException(__('Ciro edilen kişi / firma girilmelidir.'));
        }

        $previous = DB::transaction(function () use ($cheque, $status, $causer, $endorsedTo, $note): ChequeStatus {
            // Geçiş kilit altında, güncel durum üzerinden denetlenir: eşzamanlı iki
            // "karşılıksız" isteği cariye iki kez borç yazamaz.
            $locked = Cheque::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($cheque->id);
            $cheque->setRawAttributes($locked->getAttributes(), true);
            $previous = $cheque->status;

            if (! in_array($status, $previous->next(), true)) {
                throw new ShopException(__(':current durumundaki çek / senet :status yapılamaz.', ['current' => $previous->label(), 'status' => $status->label()]));
            }

            $cheque->forceFill([
                'status' => $status,
                'endorsed_to' => $status === ChequeStatus::Endorsed ? $endorsedTo : $cheque->endorsed_to,
                'notes' => $note !== null && $note !== '' ? trim(($cheque->notes ? $cheque->notes.' · ' : '').$note) : $cheque->notes,
                'status_changed_at' => now(),
            ])->save();

            if ($status->reversesCredit()) {
                $this->ledger->record($cheque->account_id, TransactionType::ChequeBounce, TransactionDirection::Debit, $cheque->amount, [
                    'source' => $cheque,
                    'description' => __(':type :serial :status', ['type' => $cheque->type->label(), 'serial' => $cheque->serial_no, 'status' => mb_strtolower($status->label())]).($note ? ' · '.$note : ''),
                    'user' => $causer,
                ]);

                $cheque->payment?->forceFill(['status' => PaymentStatus::Failed, 'failed_reason' => $status->label()])->save();
            }

            return $previous;
        });

        ChequeStatusChangedEvent::dispatch($cheque->fresh(), $previous, $causer, $note);
    }

    /**
     * Riske eklenen (henüz kesin tahsil edilmemiş) çek / senet toplamı (ana birim).
     */
    public function risk(Account $account): BigDecimal
    {
        return Money::round(Cheque::query()->withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereIn('status', ChequeStatus::atRisk())
            ->sum('amount'));
    }

    /**
     * Portföy toplamları (durum bazında, ana para biriminde).
     *
     * @return array<string, string>
     */
    public function totals(): array
    {
        return Cheque::query()
            ->groupBy('status')
            ->selectRaw('status, sum(amount) as total')
            ->pluck('total', 'status')
            ->map(fn ($v) => Money::format(Money::round($v)))
            ->all();
    }
}
