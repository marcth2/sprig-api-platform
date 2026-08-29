<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit\Rules;

use App\Console\Commands\OpenApiAudit\AuditContext;
use App\Console\Commands\OpenApiAudit\AuditFinding;
use App\Console\Commands\OpenApiAudit\AuditRule;
use App\Console\Commands\OpenApiAudit\AuditSeverity;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use ReflectionClass;
use ReflectionMethod;
use Spatie\LaravelData\Data;

/**
 * Compares each OA\Schema-decorated DTO's constructor-promoted properties against its
 * #[OA\Property] annotations by name only — not type or nullability. Catches a field
 * renamed or added on a DTO without updating its OpenAPI annotation, which no other
 * rule in this audit would notice.
 */
final class DtoSchemaDriftRule implements AuditRule
{
    public function name(): string
    {
        return 'DTO/schema drift';
    }

    public function severity(): AuditSeverity
    {
        return AuditSeverity::Error;
    }

    /** @return array<int, AuditFinding> */
    public function audit(AuditContext $context): array
    {
        $findings = [];

        foreach ($context->dtoClasses as $reflection) {
            $issues = $this->compareDtoPropertiesToSchema($reflection);

            if (! empty($issues)) {
                $findings[] = new AuditFinding($reflection->getName(), $issues);
            }
        }

        return $findings;
    }

    /**
     * @param ReflectionClass<Data> $reflection
     * @return string[]
     */
    private function compareDtoPropertiesToSchema(ReflectionClass $reflection): array
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $phpProperties = [];
        foreach ($constructor->getParameters() as $parameter) {
            $phpProperties[Str::snake($parameter->getName())] = $parameter->getName();
        }

        $annotatedProperties = $this->getAnnotatedPropertyNames($reflection, $constructor);

        $issues = [];

        foreach (array_diff_key($phpProperties, $annotatedProperties) as $snakeName => $phpName) {
            $issues[] = "property \${$phpName} has no matching #[OA\\Property(property: '{$snakeName}')]";
        }

        foreach (array_diff_key($annotatedProperties, $phpProperties) as $snakeName => $original) {
            $issues[] = "#[OA\\Property(property: '{$snakeName}')] has no matching constructor property";
        }

        return $issues;
    }

    /**
     * @param ReflectionClass<Data> $reflection
     * @return array<string, string> snake-cased property name => original annotated name
     */
    private function getAnnotatedPropertyNames(ReflectionClass $reflection, ReflectionMethod $constructor): array
    {
        $names = [];

        foreach ($constructor->getParameters() as $parameter) {
            foreach ($parameter->getAttributes(OA\Property::class) as $attribute) {
                $property = $attribute->newInstance()->property;

                $names[Str::snake($property)] = $property;
            }
        }

        foreach ($reflection->getAttributes(OA\Schema::class) as $attribute) {
            $properties = $attribute->newInstance()->properties;

            // Vendor @var claims list<Property>, but the real runtime default when `properties:` is
            // omitted is the Undefined::UNDEFINED sentinel string.
            // @phpstan-ignore function.alreadyNarrowedType
            if (! is_array($properties)) {
                continue;
            }

            foreach ($properties as $property) {
                $names[Str::snake($property->property)] = $property->property;
            }
        }

        return $names;
    }
}
