<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Themes;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Themes\ThemeActivatedEvent;

class LogThemeActivated
{
    use LogsActivity;

    public function handle(ThemeActivatedEvent $event): void
    {
        $this->logActivity(
            logName: 'panel.theme',
            causer: $event->causer,
            event: 'activated',
            message: __(':causer_name, mağaza temasını :theme_name yaptı.', ['causer_name' => $event->causer->name, 'theme_name' => $event->themeName]),
            properties: [
                'theme' => $event->themeKey,
            ],
        );
    }
}
