<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Tests\TestCase;

class AuditOpenApiSpecDtoDriftTest extends TestCase
{
    private function fixturePath(string $name): string
    {
        return base_path("tests/Fixtures/OpenApi/{$name}");
    }

    public function test_dto_schema_drift_is_reported_as_an_error(): void
    {
        $this->artisan('l5-swagger:audit', [
            '--spec-file' => $this->fixturePath('complete.yaml'),
            '--dto-dir' => base_path('tests/Fixtures/DtoDrift'),
            '--dto-namespace' => 'Tests\\Fixtures\\DtoDrift',
        ])
            ->expectsOutputToContain(
                "DriftingDto: property \$unannotated has no matching #[OA\\Property(property: 'unannotated')], "
                ."#[OA\\Property(property: 'phantom_field')] has no matching constructor property"
            )
            ->assertExitCode(1);
    }
}
