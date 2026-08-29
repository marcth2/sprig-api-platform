<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditFinding;
use App\Console\Commands\OpenApiAudit\AuditRule;
use App\Console\Commands\OpenApiAudit\AuditSeverity;

/**
 * Only api/ prefixed paths are checked — non-api paths (e.g. /up) are excluded from phantom detection.
 */
final class PhantomPathsRule implements AuditRule
{
    public function name(): string
    {
        return 'Phantom spec paths';
    }

    public function severity(): AuditSeverity
    {
        return AuditSeverity::Error;
    }

    /** @return array<int, AuditFinding> */
    public function audit(AuditContext $context): array
    {
        $routeSignatures = array_map(
            fn (array $route): string => $route['method'].':'.$route['uri'],
            $context->routes
        );

        $phantom = array_filter(
            $context->specPaths,
            fn (array $path): bool => str_starts_with($path['path'], 'api/')
                && ! in_array($path['method'].':'.$path['path'], $routeSignatures)
        );

        return array_values(array_map(
            fn (array $path): AuditFinding => new AuditFinding(
                strtoupper($path['method']).' /'.$path['path'],
                ['no matching route']
            ),
            $phantom
        ));
    }
}
