<?php

declare(strict_types=1);

namespace App\Events\Panel\System;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $steps
     */
    public function __construct(
        public readonly string $version,
        public readonly array $steps,
        public readonly User $causer,
    ) {}
}
