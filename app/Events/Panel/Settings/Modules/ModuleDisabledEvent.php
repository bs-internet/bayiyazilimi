<?php

declare(strict_types=1);

namespace App\Events\Panel\Settings\Modules;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModuleDisabledEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $moduleKey,
        public readonly string $moduleName,
        public readonly User $causer,
    ) {}
}
