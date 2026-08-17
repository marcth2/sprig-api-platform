<?php

declare(strict_types=1);

namespace App\HealthCheck\Enums;

enum ServiceStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';
}
