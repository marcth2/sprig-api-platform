<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditFinding;
use App\Console\Commands\OpenApiAudit\AuditRule;
use App\Console\Commands\OpenApiAudit\AuditSeverity;

final class IncompleteAnnotationsRule implements AuditRule
{
    public function name(): string
    {
        return 'Incomplete annotations';
    }

    public function severity(): AuditSeverity
    {
        return AuditSeverity::Warning;
    }

    /** @return array<int, AuditFinding> */
    public function audit(AuditContext $context): array
    {
        $specBySignature = [];
        foreach ($context->specPaths as $path) {
            $specBySignature[$path['method'].':'.$path['path']] = $path['operation'];
        }

        $operationIdCounts = $this->countOperationIds($context->specPaths);

        $findings = [];

        foreach ($context->routes as $route) {
            $sig = $route['method'].':'.$route['uri'];

            if (! isset($specBySignature[$sig])) {
                continue;
            }

            $operation = $specBySignature[$sig];
            $issues = [];

            $operationIdRaw = $operation['operationId'] ?? null;
            $operationId = is_string($operationIdRaw) ? $operationIdRaw : null;

            if ($operationId === null || $operationId === '') {
                $issues[] = 'missing operationId';
            } elseif (($operationIdCounts[$operationId] ?? 0) > 1) {
                $count = $operationIdCounts[$operationId];
                $issues[] = "duplicate operationId '{$operationId}' (used by {$count} operations)";
            }

            if ($this->routeHasSanctum($route['middleware'])) {
                $responses = $operation['responses'] ?? [];
                if (is_array($responses) && ! isset($responses['401']) && ! isset($responses[401])) {
                    $issues[] = 'auth:sanctum route missing 401 response';
                }
            }

            if (! empty($issues)) {
                $findings[] = new AuditFinding(strtoupper($route['method']).' /'.$route['uri'], $issues);
            }
        }

        return $findings;
    }

    /**
     * @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $specPaths
     * @return array<string, int>
     */
    private function countOperationIds(array $specPaths): array
    {
        $counts = [];

        foreach ($specPaths as $path) {
            $operationIdRaw = $path['operation']['operationId'] ?? null;

            if (! is_string($operationIdRaw) || $operationIdRaw === '') {
                continue;
            }

            $counts[$operationIdRaw] = ($counts[$operationIdRaw] ?? 0) + 1;
        }

        return $counts;
    }

    /** @param string[] $middleware */
    private function routeHasSanctum(array $middleware): bool
    {
        return (bool) array_filter(
            $middleware,
            fn (string $entry): bool => str_contains($entry, 'sanctum') || str_contains($entry, 'Authenticate')
        );
    }
}
