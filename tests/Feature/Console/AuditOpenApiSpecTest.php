<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditOpenApiSpecTest extends TestCase
{
    private function fixturePath(string $name): string
    {
        return base_path("tests/Fixtures/OpenApi/{$name}");
    }

    public function test_exits_nonzero_for_undocumented_route(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('undocumented-route.yaml'),
        ])->assertExitCode(1);
    }

    public function test_exits_nonzero_for_phantom_path(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('phantom-path.yaml'),
        ])->assertExitCode(1);
    }

    public function test_incomplete_annotation_exits_zero_by_default(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('incomplete-annotations.yaml'),
        ])->assertExitCode(0);
    }

    public function test_incomplete_annotation_exits_nonzero_with_fail_on_warnings(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('incomplete-annotations.yaml'),
            '--fail-on-warnings' => true,
        ])->assertExitCode(1);
    }

    public function test_exits_nonzero_when_spec_file_missing(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => '/nonexistent/path/openapi.yaml',
        ])->assertExitCode(1);
    }

    public function test_missing_operationid_is_warning_by_default(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('missing-operationid.yaml'),
        ])->assertExitCode(0);
    }

    public function test_missing_operationid_exits_nonzero_with_fail_on_warnings(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('missing-operationid.yaml'),
            '--fail-on-warnings' => true,
        ])->assertExitCode(1);
    }

    public function test_duplicate_operationid_is_warning_by_default(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('duplicate-operationid.yaml'),
        ])->assertExitCode(0);
    }

    public function test_duplicate_operationid_exits_nonzero_with_fail_on_warnings(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('duplicate-operationid.yaml'),
            '--fail-on-warnings' => true,
        ])->assertExitCode(1);
    }

    public function test_route_missing_api_middleware_is_warning_by_default(): void
    {
        Route::get('/api/misrouted', fn () => response()->json([]));

        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('complete.yaml'),
        ])->assertExitCode(0);
    }

    public function test_route_missing_api_middleware_exits_nonzero_with_fail_on_warnings(): void
    {
        Route::get('/api/misrouted', fn () => response()->json([]));

        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('complete.yaml'),
            '--fail-on-warnings' => true,
        ])->assertExitCode(1);
    }

    public function test_handles_null_paths_in_spec(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('null-paths.yaml'),
        ])->assertExitCode(1);
    }

    public function test_handles_null_path_item_in_spec(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('null-path-item.yaml'),
        ])->assertExitCode(1);
    }
}
