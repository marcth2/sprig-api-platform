<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use OpenApi\Attributes as OA;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
#[OA\Schema(
    schema: 'HealthAggregateResource',
    description: 'Aggregate health result across all registered services.',
    required: ['services', 'healthy', 'checked_at'],
    type: 'object',
)]
class HealthAggregateData extends Data
{
    public function __construct(
        /** @var HealthStatusData[] */
        #[OA\Property(property: 'services', type: 'array', description: 'Health status of each registered service.', items: new OA\Items(ref: '#/components/schemas/HealthStatusResource'))]
        public readonly array $services,
        #[OA\Property(property: 'healthy', type: 'boolean', description: '`true` when every service reports `ok`; `false` when any service is `degraded` or `down`.', example: true)]
        public readonly bool $healthy,
        #[OA\Property(property: 'checked_at', type: 'string', format: 'date-time', description: 'ISO 8601 timestamp (UTC) of when the check was performed.', example: '2026-06-26T12:00:00+00:00')]
        public readonly string $checkedAt,
    ) {}
}
