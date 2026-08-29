<?php

declare(strict_types=1);

namespace Tests\Unit\Console\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use App\Console\Commands\OpenApiAudit\Rules\UndocumentedRoutesRule;
use Tests\TestCase;

class UndocumentedRoutesRuleTest extends TestCase
{
    public function test_name_and_severity(): void
    {
        $rule = new UndocumentedRoutesRule;

        $this->assertSame('Undocumented routes', $rule->name());
        $this->assertSame(AuditSeverity::Error, $rule->severity());
    }

    public function test_flags_a_route_with_no_matching_spec_path(): void
    {
        $context = new AuditContext(
            specPaths: [],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']]],
            dtoClasses: [],
        );

        $findings = (new UndocumentedRoutesRule)->audit($context);

        $this->assertCount(1, $findings);
        $this->assertSame('GET /api/widgets', $findings[0]->subject);
        $this->assertSame(['not documented in the spec'], $findings[0]->issues);
    }

    public function test_does_not_flag_a_route_with_a_matching_spec_path(): void
    {
        $context = new AuditContext(
            specPaths: [['method' => 'get', 'path' => 'api/widgets', 'operation' => []]],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']]],
            dtoClasses: [],
        );

        $findings = (new UndocumentedRoutesRule)->audit($context);

        $this->assertSame([], $findings);
    }
}
