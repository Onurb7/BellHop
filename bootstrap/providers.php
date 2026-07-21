<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    ...(class_exists(Laravel\Telescope\TelescopeServiceProvider::class)
        ? [TelescopeServiceProvider::class]
        : []),
];
