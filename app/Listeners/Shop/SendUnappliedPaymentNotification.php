<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\UnappliedPaymentReceivedEvent;
use App\Models\User;
use App\Notifications\Shop\UnappliedPaymentAdminNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Siparişe uygulanamayan ödeme: siparişi görebilen aktif yöneticilere
 * (Super Admin dahil) uygulama içi + e-posta.
 */
class SendUnappliedPaymentNotification
{
    public const PERMISSION = 'panel.shop.orders.show';

    public function handle(UnappliedPaymentReceivedEvent $event): void
    {
        $recipients = User::query()
            ->admin()
            ->active()
            ->get()
            ->filter(fn (User $user) => $user->can(self::PERMISSION));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new UnappliedPaymentAdminNotification($event->order, $event->payment, $event->reason));
    }
}
