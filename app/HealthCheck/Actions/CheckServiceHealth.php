<?php

declare(strict_types=1);

namespace App\HealthCheck\Actions;

use App\HealthCheck\Data\HealthAggregateData;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use App\Shared\Data\ApiErrorData;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use OpenApi\Attributes as OA;

class CheckServiceHealth
{
    use AsAction;

    public string $commandSignature = 'health:check {service? : Service name (omit for all)}';

    public string $commandDescription = 'Check the health status of application services';

    public function __construct(private readonly HealthCheckerService $checker) {}

    public function handle(?string $service = null): HealthAggregateData|HealthStatusData
    {
        if ($service === null) {
            $statuses = $this->checker->checkAll();
            $healthy = collect($statuses)->every(fn (HealthStatusData $s) => $s->status === ServiceStatus::Ok);

            return new HealthAggregateData(
                services: $statuses,
                healthy: $healthy,
                checkedAt: now()->toIso8601String(),
            );
        }

        return $this->checker->checkOne($service);
    }

    #[OA\Get(
        path: '/api/health',
        operationId: 'getHealthAggregate',
        summary: 'All services health check',
        description: 'Returns the health status of all registered services (app, mariadb, redis) as an aggregate. Responds 200 when every service is ok; responds 503 when one or more services are degraded or down. Use this endpoint in post-deploy readiness probes.',
        security: [['sanctum' => []]],
        tags: ['HealthCheck'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Every registered service is reachable and operational.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/HealthAggregateResource',
                    examples: [
                        new OA\Examples(
                            example: 'healthy',
                            summary: 'All services healthy',
                            value: [
                                'services' => [
                                    ['service' => 'app', 'status' => 'ok', 'code' => 200, 'execution_time_ms' => 2, 'meta' => ['opcache_enabled' => true, 'debug_mode' => false, 'maintenance_mode' => false, 'memory_limit' => '128M']],
                                    ['service' => 'mariadb', 'status' => 'ok', 'code' => 200, 'execution_time_ms' => 1, 'meta' => []],
                                    ['service' => 'redis', 'status' => 'ok', 'code' => 200, 'execution_time_ms' => 1, 'meta' => []],
                                ],
                                'healthy' => true,
                                'checked_at' => '2026-06-26T12:00:00+00:00',
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No valid Bearer token was provided.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 503,
                description: 'One or more services are degraded or unreachable.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/HealthAggregateResource',
                    examples: [
                        new OA\Examples(
                            example: 'degraded',
                            summary: 'MariaDB unreachable',
                            value: [
                                'services' => [
                                    ['service' => 'app', 'status' => 'ok', 'code' => 200, 'execution_time_ms' => 2, 'meta' => ['opcache_enabled' => true, 'debug_mode' => false, 'maintenance_mode' => false, 'memory_limit' => '128M']],
                                    ['service' => 'mariadb', 'status' => 'down', 'code' => 503, 'execution_time_ms' => 2001, 'meta' => []],
                                    ['service' => 'redis', 'status' => 'ok', 'code' => 200, 'execution_time_ms' => 1, 'meta' => []],
                                ],
                                'healthy' => false,
                                'checked_at' => '2026-06-26T12:00:00+00:00',
                            ]
                        ),
                    ]
                )
            ),
        ],
    )]
    #[OA\Get(
        path: '/api/health/{service}',
        operationId: 'getServiceHealth',
        summary: 'Single service health check',
        description: 'Returns the health status of a single named service. Use for targeted diagnostics when a specific service appears unhealthy. The meta field carries service-specific detail (e.g. opcache state for app, connection latency for mariadb).',
        security: [['sanctum' => []]],
        tags: ['HealthCheck'],
        parameters: [
            new OA\Parameter(
                name: 'service',
                in: 'path',
                required: true,
                description: 'Name of the service to check. Valid values: app (Laravel application runtime), mariadb (primary database), redis (cache and session store). Returns 404 for any other value.',
                schema: new OA\Schema(type: 'string', enum: ['app', 'mariadb', 'redis'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The requested service is reachable and operational.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/HealthStatusResource',
                    examples: [
                        new OA\Examples(
                            example: 'redis-healthy',
                            summary: 'Redis healthy',
                            value: [
                                'service' => 'redis',
                                'status' => 'ok',
                                'code' => 200,
                                'execution_time_ms' => 3,
                                'meta' => [],
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No valid Bearer token was provided.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 404,
                description: 'The service name is not recognised by the health check registry.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'unknown-service',
                            summary: 'Unknown service',
                            value: ['message' => 'Unknown service']
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'The requested service is degraded or unreachable.',
                content: new OA\JsonContent(ref: '#/components/schemas/HealthStatusResource')
            ),
        ],
    )]
    public function asController(Request $request, ?string $service = null): JsonResponse
    {
        try {
            $result = $this->handle($service);
        } catch (\InvalidArgumentException) {
            return response()->json(new ApiErrorData('Unknown service'), 404);
        }

        if ($result instanceof HealthAggregateData) {
            return response()->json($result, $result->healthy ? 200 : 503);
        }

        return response()->json($result, $result->code);
    }

    public function asCommand(Command $command): int
    {
        $service = $command->argument('service');
        $service = is_string($service) ? $service : null;

        try {
            $result = $this->handle($service);
        } catch (\InvalidArgumentException) {
            $command->error('Unknown service: '.($service ?? ''));

            return Command::FAILURE;
        }

        if ($result instanceof HealthAggregateData) {
            $command->table(
                ['Service', 'Status', 'Code', 'Time (ms)'],
                array_map(
                    fn (HealthStatusData $d) => [$d->service, $d->status->value, $d->code, $d->executionTimeMs],
                    $result->services
                )
            );

            return $result->healthy ? Command::SUCCESS : Command::FAILURE;
        }

        $command->table(
            ['Service', 'Status', 'Code', 'Time (ms)'],
            [[$result->service, $result->status->value, $result->code, $result->executionTimeMs]]
        );

        if (! empty($result->meta)) {
            $command->table(['Key', 'Value'], $this->flattenMeta($result->meta));
        }

        return $result->status === ServiceStatus::Ok ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{string, string}>
     */
    private function flattenMeta(array $meta, string $prefix = ''): array
    {
        $rows = [];

        foreach ($meta as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $rows = array_merge($rows, $this->flattenMeta($value, $fullKey));
            } else {
                $rows[] = [$fullKey, match (true) {
                    is_bool($value) => $value ? 'true' : 'false',
                    is_scalar($value) => (string) $value,
                    default => '',
                }];
            }
        }

        return $rows;
    }
}
