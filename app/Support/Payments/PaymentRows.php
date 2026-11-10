<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use App\Support\Payments\Gateways\BankTransferGateway;
use Brick\Math\BigNumber;
use Illuminate\Support\Collection;

/**
 * Ödemeler listesi (üye alanı / vitrin Ödemelerim, panel müşteri detayı):
 * `payments` kayıtları + havale bekleyen siparişler (henüz ödeme kaydı yok).
 */
final class PaymentRows
{
    /**
     * @param  Collection<int, Order>  $orders  payments ilişkisi yüklü
     * @param  Collection<int, Payment>  $balancePayments  siparişten bağımsız tahsilatlar
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $orders, ?Collection $balancePayments = null): array
    {
        $rows = [];

        foreach ($orders as $order) {
            foreach ($order->payments as $payment) {
                $rows[] = $this->paymentRow($payment, $order);
            }

            $awaiting = $order->payment_method === BankTransferGateway::KEY
                && ! in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
                && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Failed], true)
                && $order->payments->doesntContain(fn (Payment $p) => $p->status === PaymentStatus::Paid);

            if ($awaiting) {
                $rows[] = [
                    'id' => 'order-'.$order->id,
                    'date' => $order->created_at?->toIso8601String(),
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'method' => $order->paymentMethodLabel(),
                    'amount' => $this->money($order->grand_total, $order->currency_code),
                    'status' => PaymentStatus::Pending->value,
                    'status_label' => __('Havale bekleniyor'),
                    'reference' => $order->number,
                    'failed_reason' => null,
                    'awaiting_transfer' => true,
                ];
            }
        }

        foreach ($balancePayments ?? [] as $payment) {
            $rows[] = $this->paymentRow($payment, null);
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(Payment $payment, ?Order $order): array
    {
        return [
            'id' => $payment->id,
            'date' => ($payment->paid_at ?? $payment->created_at)?->toIso8601String(),
            'order_id' => $order?->id,
            'order_number' => $order?->number,
            'method' => $payment->methodLabel(),
            'amount' => $this->money($payment->amount, $payment->currency_code),
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'reference' => $payment->merchant_oid,
            'failed_reason' => $payment->failed_reason,
            'awaiting_transfer' => false,
        ];
    }

    private function money(BigNumber|int|string|null $amount, string $code): string
    {
        return Money::formatIn($amount, $code);
    }
}
