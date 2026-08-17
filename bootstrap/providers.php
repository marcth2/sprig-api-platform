<?php

use App\HealthCheck\Providers\HealthCheckServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    HealthCheckServiceProvider::class,
];
