<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditFinding;
use App\Console\Commands\OpenApiAudit\AuditRule;
use App\Console\Commands\OpenApiAudit\AuditSeverity;

final class UndocumentedRoutesRule implements AuditRule
{
    public function name(): string
    {
        return 'Undocumented routes';
    }

    public function severity(): AuditSeverity
    {
        return AuditSeverity::Error;
    }

    /** @return array<int, AuditFinding> */
    public function audit(AuditContext $context): array
    {
        $specSignatures = array_map(
            fn (array $path): string => $path['method'].':'.$path['path'],
            $context->specPaths
        );

        $undocumented = array_filter(
            $context->routes,
            fn (array $route): bool => ! in_array($route['method'].':'.$route['uri'], $specSignatures)
        );

        return array_values(array_map(
            fn (array $route): AuditFinding => new AuditFinding(
                strtoupper($route['method']).' /'.$route['uri'],
                ['not documented in the spec']
            ),
            $undocumented
        ));
    }
}
