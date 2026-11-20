<?php

declare(strict_types=1);

namespace App\Events\System;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Günlük veritabanı yedeği alındı (`bayi:backup`). `path` yedek dosyasının tam yoludur.
 */
class DatabaseBackedUpEvent
{
    use Dispatchable;

    public function __construct(public readonly string $path) {}
}
