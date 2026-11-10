<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Shop\OrderStatus;
use App\Enums\Shop\PaymentStatus;
use App\Models\Order;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShopSettings;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stok rezervasyon süresi (Stok Ayarları › `stock_reservation_days`, 0 = süresiz):
 * stok sipariş anında düşüldüğü için süresi içinde ödemesi gelmeyen (zaman aşımı
 * olmayan yöntemler: havale / EFT) ve onaylanmayan (vadeli, limit aşımı) siparişler
 * kapatılır, stok serbest kalır. Kart ödemelerinin kendi zaman aşımı vardır
 * (`shop:expire-pending-payments`). Saatte bir çalışır (routes/console.php).
 */
final class ReleaseStockReservationsCommand extends Command
{
    protected $signature = 'shop:release-stock-reservations';

    protected $description = 'Stok rezervasyon süresi dolan (ödemesi gelmeyen / onaylanmayan) siparişleri kapatır ve stoğu serbest bırakır.';

    public function handle(PaymentRegistry $payments, OrderService $orders): int
    {
        $days = ShopSettings::int('stock_reservation_days');

        if ($days <= 0) {
            $this->info(__('Stok rezervasyon süresi tanımlı değil; işlem yapılmadı.'));

            return self::SUCCESS;
        }

        // Kendi zaman aşımı olmayan ödeme yöntemleri (havale vb.).
        $untimed = collect($payments->all())
            ->filter(fn (PaymentGateway $gateway) => $gateway->pendingTimeout() === null)
            ->keys()
            ->all();

        $released = 0;

        Order::query()
            ->where('created_at', '<', now()->subDays($days))
            ->where(fn (Builder $q) => $q
                ->where('status', OrderStatus::AwaitingApproval)
                ->orWhere(fn (Builder $w) => $w
                    ->where('status', OrderStatus::Pending)
                    ->where('payment_status', PaymentStatus::Pending)
                    ->whereIn('payment_method', $untimed)))
            ->each(function (Order $order) use ($orders, $days, &$released): void {
                $released += $orders->releaseReservation($order, $days) ? 1 : 0;
            });

        $this->info(__(':count siparişin stok rezervasyonu sona erdi.', ['count' => $released]));

        return self::SUCCESS;
    }
}
