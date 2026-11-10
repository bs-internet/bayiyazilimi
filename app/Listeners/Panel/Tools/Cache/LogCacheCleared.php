<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Tools\Cache;

use App\Concerns\LogsActivity;
use App\Events\Panel\Tools\Cache\CacheClearedEvent;

class LogCacheCleared
{
    use LogsActivity;

    public function handle(CacheClearedEvent $event): void
    {
        $userName = $event->causer->name;

        $cacheName = match ($event->type) {
            'application' => __('Uygulama Önbelleğini'),
            'config' => __('Yapılandırma Önbelleğini'),
            'route' => __('Rota Önbelleğini'),
            'view' => __('Görünüm Önbelleğini'),
            'event' => __('Olay Önbelleğini'),
            'compiled' => __('Derlenmiş Önbelleği'),
            'all' => __('Tüm Önbelleği'),
            default => $event->type,
        };

        $this->logActivity(
            logName: 'cache',
            causer: $event->causer,
            event: 'cleared',
            message: __(':user_name :cache_name temizledi.', ['user_name' => $userName, 'cache_name' => $cacheName]),
            properties: ['type' => $event->type],
        );
    }
}
