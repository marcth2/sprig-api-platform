<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use App\HealthCheck\Enums\ServiceStatus;
use OpenApi\Attributes as OA;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
#[OA\Schema(
    schema: 'HealthStatusResource',
    description: 'Health status of a single service.',
    required: ['service', 'status', 'code', 'execution_time_ms'],
    type: 'object',
)]
class HealthStatusData extends Data
{
    public function __construct(
        #[OA\Property(property: 'service', type: 'string', description: 'Service identifier (e.g. app, mariadb, redis).', example: 'redis')]
        public readonly string $service,
        #[OA\Property(property: 'status', type: 'string', enum: ['ok', 'degraded', 'down'], description: '`ok` — fully operational. `degraded` — reachable but performing below expectations. `down` — unreachable or threw an exception.', example: 'ok')]
        public readonly ServiceStatus $status,
        #[OA\Property(property: 'code', type: 'integer', description: 'HTTP status code mirroring the response status (200 for ok, 503 for degraded or down).', example: 200)]
        public readonly int $code,
        #[OA\Property(property: 'execution_time_ms', type: 'integer', description: 'Time taken to perform the health check in milliseconds. Values above ~500 ms may indicate connection saturation.', example: 3)]
        public readonly int $executionTimeMs,
        /** @var array<string, mixed> */
        #[OA\Property(property: 'meta', type: 'object', description: 'Service-specific diagnostic data. For app: includes opcache_enabled, debug_mode, maintenance_mode, memory_limit. Empty object for services with no extra diagnostics.', additionalProperties: new OA\AdditionalProperties)]
        public readonly array $meta = [],
    ) {}
}
