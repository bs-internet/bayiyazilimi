<?php

declare(strict_types=1);

namespace App\Listeners\Panel\Settings\Modules;

use App\Concerns\LogsActivity;
use App\Events\Panel\Settings\Modules\ModuleEnabledEvent;

class LogModuleEnabled
{
    use LogsActivity;

    public function handle(ModuleEnabledEvent $event): void
    {
        $this->logActivity(
            logName: 'panel.module',
            causer: $event->causer,
            event: 'activated',
            message: __(':causer_name, :module_name eklentisini açtı.', ['causer_name' => $event->causer->name, 'module_name' => $event->moduleName]),
            properties: [
                'module' => $event->moduleKey,
            ],
        );
    }
}
