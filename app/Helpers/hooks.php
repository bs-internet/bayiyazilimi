<?php

declare(strict_types=1);

use App\Support\Hooks\HookManager;

if (! function_exists('hooks')) {
    /**
     * Global hook yöneticisi erişimi. Modüller `hooks()->action(...)` ile
     * genişleme noktalarına bağlanır; çekirdek `hooks()->do(...)` ile tetikler.
     */
    function hooks(): HookManager
    {
        // Modül provider'ları ModuleServiceProvider'dan önce çalışır; ilk
        // erişimde de tek (singleton) örnek garanti edilir.
        app()->singletonIf(HookManager::class);

        return app(HookManager::class);
    }
}
