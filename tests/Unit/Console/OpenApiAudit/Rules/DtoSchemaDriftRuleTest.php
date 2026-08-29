<?php

declare(strict_types=1);

namespace Tests\Unit\Console\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use App\Console\Commands\OpenApiAudit\Rules\DtoSchemaDriftRule;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Tests\Fixtures\DtoDrift\Data\DriftingDto;
use Tests\TestCase;

class DtoSchemaDriftRuleTest extends TestCase
{
    /**
     * @param class-string<Data> $class
     * @return ReflectionClass<Data>
     */
    private function reflectDto(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }

    public function test_name_and_severity(): void
    {
        $rule = new DtoSchemaDriftRule;

        $this->assertSame('DTO/schema drift', $rule->name());
        $this->assertSame(AuditSeverity::Error, $rule->severity());
    }

    public function test_flags_a_dto_with_mismatched_properties(): void
    {
        $context = new AuditContext(
            specPaths: [],
            allRoutes: [],
            routes: [],
            dtoClasses: [$this->reflectDto(DriftingDto::class)],
        );

        $findings = (new DtoSchemaDriftRule)->audit($context);

        $this->assertCount(1, $findings);
        $this->assertSame(DriftingDto::class, $findings[0]->subject);
        $this->assertSame(
            [
                'property $unannotated has no matching #[OA\Property(property: \'unannotated\')]',
                "#[OA\\Property(property: 'phantom_field')] has no matching constructor property",
            ],
            $findings[0]->issues
        );
    }

    public function test_does_not_flag_a_dto_with_no_dto_classes(): void
    {
        $context = new AuditContext(specPaths: [], allRoutes: [], routes: [], dtoClasses: []);

        $findings = (new DtoSchemaDriftRule)->audit($context);

        $this->assertSame([], $findings);
    }
}
