<?php

declare(strict_types=1);

namespace App\Events\System;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Uygulamada raporlanan bir hata (canlıda): yöneticilere bildirilir. Hata
 * nesnesi değil, özeti taşınır (seri hale getirilebilir, gizli bağlam taşımaz).
 */
class ErrorReportedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $location,
        public readonly ?string $url,
        public readonly CarbonImmutable $occurredAt,
    ) {}
}
