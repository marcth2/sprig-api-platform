<?php

declare(strict_types=1);

use App\HealthCheck\Checks\ApplicationHealthCheck;
use App\HealthCheck\Checks\MariadbHealthCheck;
use App\HealthCheck\Checks\RedisHealthCheck;

return [
    'checks' => [
        ApplicationHealthCheck::class,
        MariadbHealthCheck::class,
        RedisHealthCheck::class,
    ],
];
