<?php

declare(strict_types=1);

namespace App\HealthCheck\Contracts;

use App\HealthCheck\Data\HealthStatusData;

interface HealthCheckInterface
{
    public function name(): string;

    public function check(): HealthStatusData;
}
