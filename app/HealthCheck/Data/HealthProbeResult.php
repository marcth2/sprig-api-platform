<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use App\HealthCheck\Contracts\HealthCheckMetaData;
use App\HealthCheck\Enums\ServiceState;

final readonly class HealthProbeResult
{
    public function __construct(
        public ServiceState $state = ServiceState::Ok,
        public HealthCheckMetaData $meta = new EmptyHealthMeta,
    ) {}
}
