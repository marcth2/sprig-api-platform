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
    schema: 'MariadbHealthMeta',
    description: 'Diagnostic detail returned by the mariadb health check when the connection succeeds.',
    type: 'object',
)]
class MariadbHealthMeta extends Data implements HealthCheckMetaData
{
    public function __construct(
        #[OA\Property(
            property: 'version',
            type: 'string',
            description: 'MariaDB server version string, as reported by `SELECT VERSION()`.',
            example: '10.11.0-MariaDB'
        )]
        public readonly string $version,
        #[OA\Property(
            property: 'max_connections',
            type: 'integer',
            description: 'Configured `max_connections` server variable.',
            example: 151
        )]
        public readonly int $maxConnections,
        #[OA\Property(
            property: 'threads_connected',
            type: 'integer',
            description: 'Number of client threads currently connected.',
            example: 3
        )]
        public readonly int $threadsConnected,
    ) {}
}
