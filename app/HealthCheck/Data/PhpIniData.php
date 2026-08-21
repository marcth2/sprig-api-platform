<?php

declare(strict_types=1);

namespace App\HealthCheck\Data;

use OpenApi\Attributes as OA;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
#[OA\Schema(
    schema: 'PhpIniData',
    description: 'Relevant php.ini directives read at request time.',
    type: 'object',
)]
class PhpIniData extends Data
{
    public function __construct(
        #[OA\Property(property: 'memory_limit', type: 'string', example: '128M')]
        public readonly string $memoryLimit,

        #[OA\Property(property: 'max_execution_time', type: 'string', example: '30')]
        public readonly string $maxExecutionTime,

        #[OA\Property(property: 'post_max_size', type: 'string', example: '8M')]
        public readonly string $postMaxSize,

        #[OA\Property(property: 'opcache_enabled', type: 'boolean', example: true)]
        public readonly bool $opcacheEnabled,
    ) {}
}
