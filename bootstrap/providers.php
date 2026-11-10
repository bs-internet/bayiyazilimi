<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\ShopServiceProvider;

return [
    ModuleServiceProvider::class,
    ShopServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
