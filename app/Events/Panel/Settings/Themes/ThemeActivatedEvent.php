<?php

declare(strict_types=1);

namespace App\Events\Panel\Settings\Themes;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThemeActivatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string|null  $themeKey  null = varsayılan tema
     */
    public function __construct(
        public readonly ?string $themeKey,
        public readonly string $themeName,
        public readonly User $causer,
    ) {}
}
