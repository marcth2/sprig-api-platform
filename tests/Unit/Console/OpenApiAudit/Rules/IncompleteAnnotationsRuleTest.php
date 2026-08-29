<?php

declare(strict_types=1);

namespace Tests\Unit\Console\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use App\Console\Commands\OpenApiAudit\Rules\IncompleteAnnotationsRule;
use Tests\TestCase;

class IncompleteAnnotationsRuleTest extends TestCase
{
    public function test_name_and_severity(): void
    {
        $rule = new IncompleteAnnotationsRule;

        $this->assertSame('Incomplete annotations', $rule->name());
        $this->assertSame(AuditSeverity::Warning, $rule->severity());
    }

    public function test_flags_a_route_with_no_operation_id(): void
    {
        $context = new AuditContext(
            specPaths: [['method' => 'get', 'path' => 'api/widgets', 'operation' => []]],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']]],
            dtoClasses: [],
        );

        $findings = (new IncompleteAnnotationsRule)->audit($context);

        $this->assertCount(1, $findings);
        $this->assertSame('GET /api/widgets', $findings[0]->subject);
        $this->assertSame(['missing operationId'], $findings[0]->issues);
    }

    public function test_flags_a_duplicate_operation_id(): void
    {
        $specPaths = [
            ['method' => 'get', 'path' => 'api/widgets', 'operation' => ['operationId' => 'listWidgets']],
            ['method' => 'get', 'path' => 'api/gadgets', 'operation' => ['operationId' => 'listWidgets']],
        ];
        $routes = [
            ['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']],
            ['method' => 'get', 'uri' => 'api/gadgets', 'middleware' => ['api']],
        ];

        $context = new AuditContext(specPaths: $specPaths, allRoutes: [], routes: $routes, dtoClasses: []);

        $findings = (new IncompleteAnnotationsRule)->audit($context);

        $this->assertCount(2, $findings);
        $this->assertSame(["duplicate operationId 'listWidgets' (used by 2 operations)"], $findings[0]->issues);
        $this->assertSame(["duplicate operationId 'listWidgets' (used by 2 operations)"], $findings[1]->issues);
    }

    public function test_flags_a_sanctum_route_missing_a_401_response(): void
    {
        $context = new AuditContext(
            specPaths: [[
                'method' => 'get',
                'path' => 'api/widgets',
                'operation' => ['operationId' => 'listWidgets', 'responses' => ['200' => []]],
            ]],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api', 'auth:sanctum']]],
            dtoClasses: [],
        );

        $findings = (new IncompleteAnnotationsRule)->audit($context);

        $this->assertCount(1, $findings);
        $this->assertSame(['auth:sanctum route missing 401 response'], $findings[0]->issues);
    }

    public function test_does_not_flag_a_sanctum_route_with_a_401_response(): void
    {
        $context = new AuditContext(
            specPaths: [[
                'method' => 'get',
                'path' => 'api/widgets',
                'operation' => ['operationId' => 'listWidgets', 'responses' => ['200' => [], '401' => []]],
            ]],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api', 'auth:sanctum']]],
            dtoClasses: [],
        );

        $findings = (new IncompleteAnnotationsRule)->audit($context);

        $this->assertSame([], $findings);
    }

    public function test_ignores_a_route_with_no_matching_spec_path(): void
    {
        $context = new AuditContext(
            specPaths: [],
            allRoutes: [],
            routes: [['method' => 'get', 'uri' => 'api/widgets', 'middleware' => ['api']]],
            dtoClasses: [],
        );

        $findings = (new IncompleteAnnotationsRule)->audit($context);

        $this->assertSame([], $findings);
    }
}
