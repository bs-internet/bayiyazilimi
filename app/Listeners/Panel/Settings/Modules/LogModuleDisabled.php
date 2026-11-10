<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Modules;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Modules\ModuleDisabledEvent;

class LogModuleDisabled
{
    use LogsActivity;

    public function handle(ModuleDisabledEvent $event): void
    {
        $this->logActivity(
            logName: 'panel.module',
            causer: $event->causer,
            event: 'deactivated',
            message: __(':causer_name, :module_name eklentisini kapattı.', ['causer_name' => $event->causer->name, 'module_name' => $event->moduleName]),
            properties: [
                'module' => $event->moduleKey,
            ],
        );
    }
}
