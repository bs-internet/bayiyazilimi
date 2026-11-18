<?php

declare(strict_types=1);

namespace App\Listeners\System;

use App\Events\System\ErrorReportedEvent;
use App\Models\User;
use App\Notifications\System\ErrorReportedNotification;
use App\Services\Panel\Settings\Roles\RoleService;
use Illuminate\Support\Facades\Notification;

/**
 * Raporlanan hata: `bayiyazilimi.error_alerts.to` adreslerine, adres yoksa
 * aktif Super Admin'lere (e-posta + panel bildirimi).
 */
class SendErrorReportedNotification
{
    public function handle(ErrorReportedEvent $event): void
    {
        $notification = new ErrorReportedNotification($event);
        $addresses = array_values(array_filter(array_map('trim', explode(',', (string) config('bayiyazilimi.error_alerts.to', '')))));

        if ($addresses !== []) {
            Notification::route('mail', $addresses)->notify($notification);

            return;
        }

        $recipients = User::query()->admin()->active()->role(RoleService::SUPER_ADMIN)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }
}
