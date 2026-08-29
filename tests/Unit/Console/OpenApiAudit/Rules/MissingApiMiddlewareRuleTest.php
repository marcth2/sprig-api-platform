<?php

declare(strict_types=1);

namespace Tests\Unit\Console\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use App\Console\Commands\OpenApiAudit\Rules\MissingApiMiddlewareRule;
use Tests\TestCase;

class MissingApiMiddlewareRuleTest extends TestCase
{
    public function test_name_and_severity(): void
    {
        $rule = new MissingApiMiddlewareRule;

        $this->assertSame('Routes missing api middleware', $rule->name());
        $this->assertSame(AuditSeverity::Warning, $rule->severity());
    }

    public function test_flags_an_api_prefixed_route_without_the_api_middleware(): void
    {
        $context = new AuditContext(
            specPaths: [],
            allRoutes: [['method' => 'get', 'uri' => 'api/misrouted', 'middleware' => ['web']]],
            routes: [],
            dtoClasses: [],
        );

        $findings = (new MissingApiMiddlewareRule)->audit($context);

        $this->assertCount(1, $findings);
        $this->assertSame('GET /api/misrouted', $findings[0]->subject);
        $this->assertSame(['missing api middleware'], $findings[0]->issues);
    }

    public function test_does_not_flag_a_route_with_the_api_middleware(): void
    {
        $context = new AuditContext(
            specPaths: [],
            allRoutes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']]],
            routes: [],
            dtoClasses: [],
        );

        $findings = (new MissingApiMiddlewareRule)->audit($context);

        $this->assertSame([], $findings);
    }

    public function test_does_not_flag_a_non_api_prefixed_route(): void
    {
        $context = new AuditContext(
            specPaths: [],
            allRoutes: [['method' => 'get', 'uri' => 'up', 'middleware' => []]],
            routes: [],
            dtoClasses: [],
        );

        $findings = (new MissingApiMiddlewareRule)->audit($context);

        $this->assertSame([], $findings);
    }
}
