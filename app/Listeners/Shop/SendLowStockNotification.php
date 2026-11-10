<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\StockLowEvent;
use App\Models\User;
use App\Notifications\Shop\LowStockNotification;
use App\Services\Shop\ShopSettings;
use Illuminate\Support\Facades\Notification;

/**
 * Kritik stok bildirimi: Stok Durumu ekranını görme yetkisi olan aktif
 * yöneticilere (Super Admin dahil) uygulama içi + e-posta.
 */
class SendLowStockNotification
{
    public const PERMISSION = 'panel.shop.stock.index';

    public function handle(StockLowEvent $event): void
    {
        if (! ShopSettings::bool('low_stock_notify')) {
            return;
        }

        $recipients = User::query()
            ->admin()
            ->active()
            ->get()
            ->filter(fn (User $user) => $user->can(self::PERMISSION));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new LowStockNotification($event->product));
    }
}
