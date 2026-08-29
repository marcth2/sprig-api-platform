<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditFinding;
use App\Console\Commands\OpenApiAudit\AuditRule;
use App\Console\Commands\OpenApiAudit\AuditSeverity;

/**
 * A route under api/ that skipped the api middleware group is invisible to every other
 * check in this audit — it never reaches AuditContext::$routes, so it can't be flagged
 * undocumented either. That's exactly how a misrouted domain would pass the audit clean
 * while fully undocumented. Reads AuditContext::$allRoutes (unfiltered), not $routes.
 */
final class MissingApiMiddlewareRule implements AuditRule
{
    public function name(): string
    {
        return 'Routes missing api middleware';
    }

    public function severity(): AuditSeverity
    {
        return AuditSeverity::Warning;
    }

    /** @return array<int, AuditFinding> */
    public function audit(AuditContext $context): array
    {
        $missing = array_filter(
            $context->allRoutes,
            fn (array $route): bool => str_starts_with($route['uri'], 'api/') && ! in_array('api', $route['middleware'])
        );

        return array_values(array_map(
            fn (array $route): AuditFinding => new AuditFinding(
                strtoupper($route['method']).' /'.$route['uri'],
                ['missing api middleware']
            ),
            $missing
        ));
    }
}
