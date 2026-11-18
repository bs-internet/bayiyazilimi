<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Events\Shop\DueDigestReadyEvent;
use App\Models\User;
use App\Notifications\Shop\DueDigestNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Günlük vade özeti, cariyi görme yetkisi olan (`panel.shop.ledger.index`;
 * Super Admin dahil) aktif yöneticilere tek e-posta olarak gider.
 */
class SendDueDigest
{
    public function handle(DueDigestReadyEvent $event): void
    {
        $admins = User::query()
            ->admin()
            ->active()
            ->get()
            ->filter(fn (User $user) => $user->can('panel.shop.ledger.index'));

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new DueDigestNotification($event->rows, $event->overdue, $event->upcoming));
        }
    }
}
