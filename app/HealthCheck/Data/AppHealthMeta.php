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
    schema: 'AppHealthMeta',
    description: 'Diagnostic detail returned by the app health check.',
    type: 'object',
)]
class AppHealthMeta extends Data implements HealthCheckMetaData
{
    /** @param list<string> $degradedReasons */
    public function __construct(
        #[OA\Property(
            property: 'app_version',
            type: 'string',
            description: 'Application version from `config(\'app.version\')`.',
            example: '1.2.0'
        )]
        public readonly string $appVersion,

        #[OA\Property(
            property: 'php_version',
            type: 'string',
            description: 'PHP runtime version.',
            example: '8.4.0'
        )]
        public readonly string $phpVersion,

        #[OA\Property(
            property: 'framework_version',
            type: 'string',
            description: 'Laravel framework version.',
            example: '13.25.0'
        )]
        public readonly string $frameworkVersion,

        #[OA\Property(
            property: 'environment',
            type: 'string',
            description: 'Application environment (e.g. local, testing, production).',
            example: 'production'
        )]
        public readonly string $environment,

        #[OA\Property(
            property: 'maintenance_mode',
            type: 'boolean',
            description: 'Whether the application is currently down for maintenance.',
            example: false
        )]
        public readonly bool $maintenanceMode,

        #[OA\Property(
            property: 'degraded_reasons',
            type: 'array',
            description: <<<'TEXT'
                Reasons the status is degraded (maintenance_mode, debug_enabled, opcache_disabled,
                memory_limit_low). Empty when ok.
                TEXT,
            items: new OA\Items(type: 'string')
        )]
        public readonly array $degradedReasons,

        #[OA\Property(property: 'php_ini', ref: '#/components/schemas/PhpIniData')]
        public readonly PhpIniData $phpIni,
    ) {}
}
