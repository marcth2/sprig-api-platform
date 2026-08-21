<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use App\HealthCheck\Contracts\HealthCheckMetaData;
use OpenApi\Attributes as OA;
use Spatie\LaravelData\Data;

#[OA\Schema(
    schema: 'EmptyHealthMeta',
    description: 'No service-specific diagnostic data. Used for the down/failure path, and for any service with'
        .' nothing extra to report.',
    type: 'object',
)]
class EmptyHealthMeta extends Data implements HealthCheckMetaData {}
