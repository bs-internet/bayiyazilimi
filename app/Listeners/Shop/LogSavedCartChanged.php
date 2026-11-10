<?php

declare(strict_types=1);

namespace App\Listeners\Shop;

use App\Concerns\LogsActivity;
use App\Events\Shop\SavedCartChangedEvent;

class LogSavedCartChanged
{
    use LogsActivity;

    public function handle(SavedCartChangedEvent $event): void
    {
        $name = $event->savedCart->name;

        $message = match ($event->action) {
            'created' => __('":name" sepetini kaydetti.', ['name' => $name]),
            'updated' => __('":name" kayıtlı sepetini güncelledi.', ['name' => $name]),
            'loaded' => __('":name" kayıtlı sepetini sepete yükledi.', ['name' => $name]),
            'deleted' => __('":name" kayıtlı sepetini sildi.', ['name' => $name]),
            default => __('":name" kayıtlı sepetinde işlem yaptı.', ['name' => $name]),
        };

        $this->logActivity(
            logName: 'saved_cart',
            causer: $event->causer,
            event: $event->action,
            message: __(':name, :message', ['name' => $event->causer->name, 'message' => $message]),
            // Silinen kayıt konu olarak bağlanmaz (morph hedefi kalmaz).
            subject: $event->action === 'deleted' ? null : $event->savedCart,
            properties: ['items' => $event->savedCart->itemCount()],
        );
    }
}
