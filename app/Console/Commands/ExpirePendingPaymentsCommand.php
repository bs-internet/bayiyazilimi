<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Shop\OrderService;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentRegistry;
use App\Support\Payments\ReconcilesPayments;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Yarım kalan ödemeler: sürücüsünün zaman aşımını (`pendingTimeout`) geçmiş
 * bekleyen siparişler "Ödeme Başarısız" olur ve stok geri eklenir; siparişten
 * bağımsız bekleyen cari tahsilatlar (ör. kartla bakiye ödemesi) başarısız sayılır.
 * Havalenin zaman aşımı yoktur. 5 dakikada bir çalışır (routes/console.php).
 */
final class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'shop:expire-pending-payments';

    protected $description = 'Zaman aşımına uğrayan bekleyen (kart) ödemeleri başarısız sayar ve stoğu geri ekler.';

    public function handle(PaymentRegistry $payments, OrderService $orders): int
    {
        $expired = 0;

        /** @var PaymentGateway $gateway */
        foreach ($payments->all() as $key => $gateway) {
            $minutes = $gateway->pendingTimeout();

            if ($minutes === null || $minutes <= 0) {
                continue;
            }

            $cutoff = now()->subMinutes($minutes);
            $reason = __('Ödeme zaman aşımına uğradı.');

            // Süre, siparişin değil bekleyen ödemenin son hareketinden sayılır
            // (ödeme sayfası her açılışta sağlayıcıdan yeni oturum alır).
            Order::query()
                ->where('payment_method', $key)
                ->where('status', OrderStatus::Pending)
                ->where('payment_status', PaymentStatus::Pending)
                ->where('created_at', '<', $cutoff)
                ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Pending)->where('updated_at', '>=', $cutoff))
                ->each(function (Order $order) use ($orders, $gateway, $reason, &$expired): void {
                    if ($this->reconciled($gateway, $order->payments()->where('status', PaymentStatus::Pending)->get())) {
                        $order->refresh();
                    }

                    if ($order->status !== OrderStatus::Pending || $order->payment_status !== PaymentStatus::Pending) {
                        return;
                    }

                    $orders->markPaymentFailed($order, $reason);
                    $expired++;
                });

            Payment::query()
                ->whereNull('order_id')
                ->where('method', $key)
                ->where('status', PaymentStatus::Pending)
                ->where('updated_at', '<', $cutoff)
                ->get()
                ->each(function (Payment $payment) use ($gateway, $reason, &$expired): void {
                    $this->reconciled($gateway, collect([$payment]));

                    // Koşullu: mutabakat ya da gecikmeli bildirim bu arada sonuçlandırdıysa dokunulmaz.
                    $expired += Payment::query()->whereKey($payment->id)->where('status', PaymentStatus::Pending)
                        ->update(['status' => PaymentStatus::Failed, 'failed_reason' => $reason]);
                });
        }

        $this->info(__(':count bekleyen ödeme zaman aşımıyla kapatıldı.', ['count' => $expired]));

        return self::SUCCESS;
    }

    /**
     * Sağlayıcıdan sorgulanabilen sürücüde bekleyen ödemelerin sonucu sorulur;
     * sağlayıcı hatası zaman aşımını durdurmaz.
     *
     * @param  Collection<int, Payment>  $payments
     */
    private function reconciled(PaymentGateway $gateway, Collection $payments): bool
    {
        if (! $gateway instanceof ReconcilesPayments || $payments->isEmpty()) {
            return false;
        }

        foreach ($payments as $payment) {
            try {
                $gateway->reconcile($payment);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return true;
    }
}
