<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use App\HealthCheck\Contracts\HealthCheckMetaData;
use OpenApi\Attributes as OA;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
#[OA\Schema(
    schema: 'RedisHealthMeta',
    description: 'Diagnostic detail returned by the redis health check when the connection succeeds.',
    type: 'object',
)]
class RedisHealthMeta extends Data implements HealthCheckMetaData
{
    public function __construct(
        #[OA\Property(
            property: 'version',
            type: 'string',
            description: 'Redis server version string, as reported by `INFO`.',
            example: '7.0.0'
        )]
        public readonly string $version,
        #[OA\Property(
            property: 'used_memory',
            type: 'string',
            description: <<<'TEXT'
                Used memory in bytes, as reported by `INFO` (kept as a string to match Redis's own
                representation).
                TEXT,
            example: '1048576'
        )]
        public readonly string $usedMemory,
        #[OA\Property(
            property: 'connected_clients',
            type: 'integer',
            description: 'Number of client connections currently connected to Redis.',
            example: 4
        )]
        public readonly int $connectedClients,
    ) {}
}
