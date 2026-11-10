<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Enums\Shop\CollectionType;
use App\Enums\Shop\PaymentStatus;
use App\Enums\Shop\TransactionDirection;
use App\Enums\Shop\TransactionType;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Yöneticinin elle yazdığı cari kayıtlar: tahsilat (nakit, havale, POS, mahsup,
 * çek / senet), borç / alacak dekontu, açılış bakiyesi ve bunların iptali.
 */
class CollectionService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ChequeService $cheques,
    ) {}

    /**
     * Tahsilat: ödeme kaydı (siparişten bağımsız) + cari alacak. Tutar ana para birimindedir.
     * Çek / senette portföy kaydı da açılır (seri no, vade zorunlu).
     *
     * @param  array{type: string, amount: float|string, date?: ?string, description?: ?string, serial_no?: ?string, bank_name?: ?string, branch?: ?string, drawer?: ?string, due_date?: ?string}  $data
     */
    public function collect(Account $account, User $causer, array $data, ?UploadedFile $image = null): Payment
    {
        $type = CollectionType::from($data['type']);

        if ($type->isInstrument() && ! ShopSettings::bool('cheques_enabled')) {
            throw new ShopException(__('Çek / senet tahsilatı kapalı (Cari Ayarları).'));
        }

        $amount = Money::round((string) $data['amount']);

        if (! $amount->isPositive()) {
            throw new ShopException(__('Tahsilat tutarı sıfırdan büyük olmalıdır.'));
        }

        if ($type->isInstrument() && (empty($data['serial_no']) || empty($data['due_date']))) {
            throw new ShopException(__('Çek / senet için seri no ve vade tarihi girilmelidir.'));
        }

        return DB::transaction(function () use ($account, $causer, $data, $type, $amount, $image) {
            $payment = Payment::query()->create([
                'account_id' => $account->id,
                'order_id' => null,
                'method' => $type->value,
                'provider' => 'manual',
                'merchant_oid' => Payment::newReference('T'),
                'amount' => $amount,
                'currency_code' => Money::baseCode(),
                'status' => PaymentStatus::Paid,
                'note' => $data['description'] ?? null,
                'user_id' => $causer->id,
                'paid_at' => ! empty($data['date']) ? $data['date'] : now(),
            ]);

            if ($type->isInstrument()) {
                $this->cheques->createForPayment($payment, $type, $causer, $data, $image);
            }

            $this->ledger->targetOrders($payment, $data['order_ids'] ?? []);
            $this->ledger->recordPayment($payment);

            return $payment;
        });
    }

    /**
     * Borç / alacak dekontu ya da açılış bakiyesi.
     */
    public function adjust(Account $account, User $causer, TransactionDirection $direction, BigNumber|int|string $amount, ?string $date, string $description, bool $opening = false): AccountTransaction
    {
        return $this->ledger->record($account, $opening ? TransactionType::Opening : TransactionType::Adjustment, $direction, Money::round($amount), [
            'date' => $date,
            'description' => $description,
            'user' => $causer,
        ]);
    }

    /**
     * Elle yazılan kaydın iptali (ters kayıt). Sipariş, sipariş ödemesi ve iade
     * hareketleri belgesinden yönetilir (sipariş / iade ekranı).
     */
    public function reverse(AccountTransaction $transaction, User $causer): void
    {
        $source = $transaction->source;
        $manualPayment = $source instanceof Payment && $source->order_id === null && $source->provider === 'manual';

        // Çek / senet tahsilatı portföyden yönetilir (karşılıksız / iade).
        if ($manualPayment && CollectionType::tryFrom($source->method)?->isInstrument()) {
            throw new ShopException(__('Çek / senet tahsilatı Çek / Senet portföyünden yönetilir (karşılıksız / iade).'));
        }

        // Dış sistemden (REST API) belgesiz yazılan tahsilat da buradan ters çevrilir.
        $external = $transaction->type === TransactionType::Payment && $transaction->isExternal();

        if (! $manualPayment && ! $external && ! in_array($transaction->type, [TransactionType::Adjustment, TransactionType::Opening], true)) {
            throw new ShopException(__('Bu hareket belgesinden yönetilir (sipariş, ödeme ya da iade ekranı).'));
        }

        DB::transaction(function () use ($transaction, $causer, $source, $manualPayment) {
            if ($manualPayment) {
                // Çift tıklama: ödeme kilitli yeniden okunur; ikinci istek iptal edilmiş ödemeyi görür.
                $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($source->id);

                if ($payment->status !== PaymentStatus::Paid) {
                    throw new ShopException(__('Bu cari hareketin ters kaydı zaten yapılmış.'));
                }

                $payment->forceFill(['status' => PaymentStatus::Cancelled])->save();
            }

            // Ters kayıt koşullu yazılır: aynı hareket iki kez ters çevrilemez.
            $this->ledger->reverse($transaction, $causer);
        });
    }
}
