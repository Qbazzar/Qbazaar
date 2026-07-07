<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    HorizonServiceProvider::class,
    // TelescopeServiceProvider is registered conditionally in AppServiceProvider
    // so production deploys with --no-dev don't fail on a missing class.
];
