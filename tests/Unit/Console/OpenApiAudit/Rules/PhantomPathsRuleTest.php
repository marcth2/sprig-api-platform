<?php

declare(strict_types=1);

namespace Tests\Unit\Console\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use App\Console\Commands\OpenApiAudit\Rules\PhantomPathsRule;
use Tests\TestCase;

class PhantomPathsRuleTest extends TestCase
{
    public function test_name_and_severity(): void
    {
        $rule = new PhantomPathsRule;

        $this->assertSame('Phantom spec paths', $rule->name());
        $this->assertSame(AuditSeverity::Error, $rule->severity());
    }

    public function test_flags_an_api_spec_path_with_no_matching_route(): void
    {
        $context = new AuditContext(
            specPaths: [['method' => 'get', 'path' => 'api/widgets', 'operation' => []]],
            allRoutes: [],
            routes: [],
            dtoClasses: [],
        );

        $findings = (new PhantomPathsRule)->audit($context);

        $this->assertCount(1, $findings);
        $this->assertSame('GET /api/widgets', $findings[0]->subject);
        $this->assertSame(['no matching route'], $findings[0]->issues);
    }

    public function test_does_not_flag_a_non_api_spec_path(): void
    {
        $context = new AuditContext(
            specPaths: [['method' => 'get', 'path' => 'up', 'operation' => []]],
            allRoutes: [],
            routes: [],
            dtoClasses: [],
        );

        $findings = (new PhantomPathsRule)->audit($context);

        $this->assertSame([], $findings);
    }

    public function test_does_not_flag_an_api_spec_path_with_a_matching_route(): void
    {
        $context = new AuditContext(
            specPaths: [['method' => 'get', 'path' => 'api/widgets', 'operation' => []]],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']]],
            dtoClasses: [],
        );

        $findings = (new PhantomPathsRule)->audit($context);

        $this->assertSame([], $findings);
    }
}
