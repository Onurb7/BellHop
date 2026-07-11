<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    ...(class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)
        ? [App\Providers\TelescopeServiceProvider::class]
        : []),
];
