<?php

declare(strict_types=1);

namespace App\Support\Payments\Gateways;

use App\Enums\Shop\LimitAction;
use App\Enums\Shop\OrderStatus;
use App\Exceptions\Shop\ShopException;
use App\Models\Account;
use App\Models\Order;
use App\Services\Shop\CreditService;
use App\Services\Shop\LedgerService;
use App\Support\Ledger\CreditTerms;
use App\Support\Money;
use App\Support\Payments\BaseGateway;
use App\Support\Payments\PaymentContext;
use App\Support\Payments\PaymentStart;

/**
 * Cari hesaba (vadeli). Yalnız vadeli çalışan bayiye sunulur. Limit içinde ve
 * vadesi geçmiş borç yoksa sipariş doğrudan "Hazırlanıyor" olur ve borç vade
 * tarihiyle cariye yazılır; değilse hesabın ayarına göre "Onay Bekliyor"a
 * düşer ya da engellenir.
 */
final class OnAccountGateway extends BaseGateway
{
    public const KEY = 'on_account';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Cari hesaba (vadeli)');
    }

    public function description(PaymentContext $context): ?string
    {
        $account = $context->account;

        if ($account === null) {
            return null;
        }

        $terms = CreditTerms::for($account);
        $available = app(CreditService::class)->available($account);

        return __('Sipariş tutarı carinize :days gün vadeyle borç yazılır.', ['days' => $terms->termDays])
            .($available !== null ? ' '.__('Kullanılabilir limitiniz: :amount.', ['amount' => Money::format(Money::max(0, $available))]) : '');
    }

    public function icon(): string
    {
        return 'wallet';
    }

    public function available(PaymentContext $context): bool
    {
        return $context->purpose === PaymentContext::PURPOSE_ORDER
            && $context->account !== null
            && CreditTerms::for($context->account)->isCredit();
    }

    public function start(Order $order): PaymentStart
    {
        $account = Account::query()->with('dealerGroup')->lockForUpdate()->find($order->account_id);

        if ($account === null || ! CreditTerms::for($account)->isCredit()) {
            throw new ShopException(__('Hesabınız vadeli çalışmıyor; lütfen başka bir ödeme yöntemi seçin.'));
        }

        $ledger = app(LedgerService::class);
        // Kilitli okuma: eşzamanlı başka siparişin (aynı hesap) borcu / onay bekleyeni görülür.
        $decision = app(CreditService::class)->decide($account, $ledger->orderAmount($order, $order->grand_total), $order, locking: true);

        if ($decision['action'] === LimitAction::Block) {
            throw new ShopException(__('Vadeli sipariş verilemiyor: :reason. Başka bir ödeme yöntemi seçebilirsiniz.', ['reason' => $decision['reason']]));
        }

        if ($decision['action'] === LimitAction::Approval) {
            $order->forceFill(['status' => OrderStatus::AwaitingApproval])->save();
            $order->histories()->create([
                'status' => OrderStatus::AwaitingApproval,
                'note' => __('Vadeli sipariş onaya düştü: :reason.', ['reason' => $decision['reason']]),
            ]);

            return PaymentStart::none(__('Siparişiniz alındı (:number) ve onaya gönderildi: :reason.', ['number' => $order->number, 'reason' => mb_lcfirst((string) $decision['reason'])]));
        }

        $due = CreditTerms::for($account)->dueDate();
        $order->forceFill(['status' => OrderStatus::Processing, 'due_date' => $due])->save();
        $order->histories()->create([
            'status' => OrderStatus::Processing,
            'note' => __('Vadeli sipariş onaylandı; vade :date.', ['date' => $due->format('d.m.Y')]),
        ]);
        $ledger->recordOrder($order);

        return PaymentStart::none(__('Siparişiniz alındı (:number); vade tarihi :date.', ['number' => $order->number, 'date' => $due->format('d.m.Y')]));
    }
}
