<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use ReflectionClass;
use ReflectionMethod;
use Spatie\LaravelData\Data;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class AuditOpenApiSpec extends Command
{
    protected $signature = 'l5-swagger:audit
        {--fail-on-warnings : Exit non-zero on warnings (incomplete annotations, missing api middleware)}
        {--spec-file= : Path to OpenAPI spec file (defaults to configured l5-swagger output)}
        {--dto-dir= : Directory to scan for DTOs (defaults to the app directory)}
        {--dto-namespace= : Base namespace matching --dto-dir (defaults to App\\)}';

    protected $description = <<<'TEXT'
        Audit OpenAPI spec — undocumented routes, phantom paths, DTO/schema drift,
        routes missing api middleware, incomplete annotations
        TEXT;

    public function handle(): int
    {
        $specFileOption = $this->option('spec-file');
        $specFile = is_string($specFileOption) ? $specFileOption : storage_path('api-docs/openapi.yaml');

        if (! file_exists($specFile)) {
            $this->error("Spec file not found: {$specFile}");
            $this->line('Run: php artisan l5-swagger:generate');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $spec */
        $spec = Yaml::parseFile($specFile);
        $specPaths = $this->extractSpecPaths($spec);
        $allRoutes = $this->getAllRoutes();
        $routes = $this->filterApiRoutes($allRoutes);

        $undocumented = $this->findUndocumented($routes, $specPaths);
        $phantom = $this->findPhantom($routes, $specPaths);
        $incomplete = $this->findIncomplete($routes, $specPaths);
        $missingApiMiddleware = $this->findMissingApiMiddleware($allRoutes);

        $dtoDirOption = $this->option('dto-dir');
        $dtoDir = is_string($dtoDirOption) ? $dtoDirOption : app_path();

        $dtoNamespaceOption = $this->option('dto-namespace');
        $dtoNamespace = is_string($dtoNamespaceOption) ? $dtoNamespaceOption : 'App\\';

        $dtoSchemaDrift = $this->findDtoSchemaDrift($dtoDir, $dtoNamespace);

        $this->reportUndocumented($undocumented);
        $this->reportPhantom($phantom);
        $this->reportDtoSchemaDrift($dtoSchemaDrift);
        $this->reportMissingApiMiddleware($missingApiMiddleware);
        $this->reportIncomplete($incomplete);

        $hasErrors = count($undocumented) > 0 || count($phantom) > 0 || count($dtoSchemaDrift) > 0;
        $hasWarnings = count($incomplete) > 0 || count($missingApiMiddleware) > 0;

        if ($hasErrors) {
            $total = count($undocumented) + count($phantom) + count($dtoSchemaDrift);
            $this->newLine();
            $this->error("Audit failed: {$total} error(s).");

            return self::FAILURE;
        }

        if ($hasWarnings && $this->option('fail-on-warnings')) {
            $totalWarnings = count($incomplete) + count($missingApiMiddleware);
            $this->newLine();
            $this->error("Audit failed: {$totalWarnings} warning(s) found (--fail-on-warnings).");

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Check', 'Result'],
            [
                ['Routes audited', (string) count($routes)],
                ['Spec paths', (string) count($specPaths)],
                ['Undocumented routes', (string) count($undocumented)],
                ['Phantom spec paths', (string) count($phantom)],
                ['DTO/schema drift', (string) count($dtoSchemaDrift)],
                [
                    'Routes missing api middleware',
                    count($missingApiMiddleware) > 0 ? count($missingApiMiddleware).' warning(s)' : '0',
                ],
                ['Incomplete annotations', count($incomplete) > 0 ? count($incomplete).' warning(s)' : '0'],
            ]
        );

        $totalWarnings = count($incomplete) + count($missingApiMiddleware);
        $warningNote = $hasWarnings ? " ({$totalWarnings} warning(s))" : '';
        $this->info("Audit passed{$warningNote}.");

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<int, array{method: string, path: string, operation: array<string, mixed>}>
     */
    private function extractSpecPaths(array $spec): array
    {
        $paths = [];
        $pathsData = $spec['paths'] ?? null;

        if (! is_array($pathsData)) {
            return [];
        }

        foreach ($pathsData as $path => $pathItem) {
            $normalizedPath = ltrim((string) $path, '/');

            if (! is_array($pathItem)) {
                continue;
            }

            foreach ($pathItem as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'])) {
                    continue;
                }

                /** @var array<string, mixed> $operationData */
                $operationData = is_array($operation) ? $operation : [];

                $paths[] = [
                    'method' => (string) $method,
                    'path' => $normalizedPath,
                    'operation' => $operationData,
                ];
            }
        }

        return $paths;
    }

    /**
     * All routes except the l5-swagger UI/docs routes themselves.
     *
     * @return array<int, array{method: string, uri: string, middleware: string[]}>
     */
    private function getAllRoutes(): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var string[] $middleware */
            $middleware = $route->middleware();

            if ($this->isL5SwaggerRoute($middleware)) {
                continue;
            }

            /** @var string[] $httpMethods */
            $httpMethods = $route->methods();

            foreach ($httpMethods as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $routes[] = [
                    'method' => strtolower($method),
                    'uri' => $route->uri(),
                    'middleware' => $middleware,
                ];
            }
        }

        return $routes;
    }

    /**
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $routes
     * @return array<int, array{method: string, uri: string, middleware: string[]}>
     */
    private function filterApiRoutes(array $routes): array
    {
        return array_values(array_filter(
            $routes,
            fn (array $route): bool => in_array('api', $route['middleware'])
        ));
    }

    /** @param string[] $middleware */
    private function isL5SwaggerRoute(array $middleware): bool
    {
        foreach ($middleware as $middlewareEntry) {
            if (str_contains($middlewareEntry, 'L5Swagger')) {
                return true;
            }
        }

        return false;
    }

    /**
     * A route under api/ that skipped the api middleware group is invisible to every other
     * check below — it never reaches $routes, so it can't be flagged undocumented either.
     * That's exactly how a misrouted domain would pass the audit clean while fully undocumented.
     *
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $routes
     * @return array<int, array{method: string, uri: string, middleware: string[]}>
     */
    private function findMissingApiMiddleware(array $routes): array
    {
        return array_values(array_filter(
            $routes,
            fn (array $route): bool => str_starts_with($route['uri'], 'api/') && ! in_array('api', $route['middleware'])
        ));
    }

    /**
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $routes
     * @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $specPaths
     * @return array<int, array{method: string, uri: string, middleware: string[]}>
     */
    private function findUndocumented(array $routes, array $specPaths): array
    {
        $specSignatures = array_map(fn (array $path): string => $path['method'].':'.$path['path'], $specPaths);

        return array_values(array_filter(
            $routes,
            fn (array $route): bool => ! in_array($route['method'].':'.$route['uri'], $specSignatures)
        ));
    }

    /**
     * Only api/ prefixed paths are checked — non-api paths (e.g. /up) are excluded from phantom detection.
     *
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $routes
     * @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $specPaths
     * @return array<int, array{method: string, path: string, operation: array<string, mixed>}>
     */
    private function findPhantom(array $routes, array $specPaths): array
    {
        $routeSignatures = array_map(fn (array $route): string => $route['method'].':'.$route['uri'], $routes);

        return array_values(array_filter(
            $specPaths,
            fn (array $path): bool => str_starts_with($path['path'], 'api/')
                && ! in_array($path['method'].':'.$path['path'], $routeSignatures)
        ));
    }

    /**
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $routes
     * @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $specPaths
     * @return array<int, array{route: string, issues: string[]}>
     */
    private function findIncomplete(array $routes, array $specPaths): array
    {
        $specBySignature = [];
        foreach ($specPaths as $path) {
            $specBySignature[$path['method'].':'.$path['path']] = $path['operation'];
        }

        $operationIdCounts = $this->countOperationIds($specPaths);

        $issues = [];

        foreach ($routes as $route) {
            $sig = $route['method'].':'.$route['uri'];

            if (! isset($specBySignature[$sig])) {
                continue;
            }

            $operation = $specBySignature[$sig];
            $routeIssues = [];

            $operationIdRaw = $operation['operationId'] ?? null;
            $operationId = is_string($operationIdRaw) ? $operationIdRaw : null;

            if ($operationId === null || $operationId === '') {
                $routeIssues[] = 'missing operationId';
            } elseif (($operationIdCounts[$operationId] ?? 0) > 1) {
                $count = $operationIdCounts[$operationId];
                $routeIssues[] = "duplicate operationId '{$operationId}' (used by {$count} operations)";
            }

            if ($this->routeHasSanctum($route['middleware'])) {
                $responses = $operation['responses'] ?? [];
                if (is_array($responses) && ! isset($responses['401']) && ! isset($responses[401])) {
                    $routeIssues[] = 'auth:sanctum route missing 401 response';
                }
            }

            if (! empty($routeIssues)) {
                $issues[] = [
                    'route' => strtoupper($route['method']).' /'.$route['uri'],
                    'issues' => $routeIssues,
                ];
            }
        }

        return $issues;
    }

    /**
     * @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $specPaths
     * @return array<string, int>
     */
    private function countOperationIds(array $specPaths): array
    {
        $counts = [];

        foreach ($specPaths as $path) {
            $operationIdRaw = $path['operation']['operationId'] ?? null;

            if (! is_string($operationIdRaw) || $operationIdRaw === '') {
                continue;
            }

            $counts[$operationIdRaw] = ($counts[$operationIdRaw] ?? 0) + 1;
        }

        return $counts;
    }

    /** @param string[] $middleware */
    private function routeHasSanctum(array $middleware): bool
    {
        return (bool) array_filter(
            $middleware,
            fn (string $entry): bool => str_contains($entry, 'sanctum') || str_contains($entry, 'Authenticate')
        );
    }

    /**
     * Compares each OA\Schema-decorated DTO's constructor-promoted properties against its
     * #[OA\Property] annotations by name only — not type or nullability. Catches a field
     * renamed or added on a DTO without updating its OpenAPI annotation, which no other
     * check in this command (or the rest of the pipeline) would notice.
     *
     * @return array<int, array{class: string, issues: string[]}>
     */
    private function findDtoSchemaDrift(string $dtoDir, string $dtoNamespace): array
    {
        $results = [];

        foreach ($this->getSchemaDtoClasses($dtoDir, $dtoNamespace) as $reflection) {
            $issues = $this->compareDtoPropertiesToSchema($reflection);

            if (! empty($issues)) {
                $results[] = [
                    'class' => $reflection->getName(),
                    'issues' => $issues,
                ];
            }
        }

        return $results;
    }

    /** @return array<int, ReflectionClass<Data>> */
    private function getSchemaDtoClasses(string $dtoDir, string $dtoNamespace): array
    {
        $classes = [];

        $finder = (new Finder)->files()->in($dtoDir)->path('Data')->name('*.php');

        foreach ($finder as $file) {
            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = rtrim($dtoNamespace, '\\').'\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isSubclassOf(Data::class) || empty($reflection->getAttributes(OA\Schema::class))) {
                continue;
            }

            $classes[] = $reflection;
        }

        return $classes;
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

    /** @param array<int, array{method: string, uri: string, middleware: string[]}> $undocumented */
    private function reportUndocumented(array $undocumented): void
    {
        if (empty($undocumented)) {
            return;
        }

        $this->newLine();
        $this->line('<fg=red>Undocumented routes ('.count($undocumented).'):</>');
        $this->table(
            ['Method', 'URI'],
            array_map(fn (array $route): array => [strtoupper($route['method']), '/'.$route['uri']], $undocumented)
        );
    }

    /** @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $phantom */
    private function reportPhantom(array $phantom): void
    {
        if (empty($phantom)) {
            return;
        }

        $this->newLine();
        $this->line('<fg=red>Phantom spec paths with no matching route ('.count($phantom).'):</>');
        $this->table(
            ['Method', 'Path'],
            array_map(fn (array $path): array => [strtoupper($path['method']), '/'.$path['path']], $phantom)
        );
    }

    /** @param array<int, array{class: string, issues: string[]}> $dtoSchemaDrift */
    private function reportDtoSchemaDrift(array $dtoSchemaDrift): void
    {
        if (empty($dtoSchemaDrift)) {
            return;
        }

        $this->newLine();
        $this->line('<fg=red>DTO/schema drift ('.count($dtoSchemaDrift).'):</>');

        foreach ($dtoSchemaDrift as $item) {
            $this->line('  <fg=red>'.$item['class'].':</> '.implode(', ', $item['issues']));
        }
    }

    /** @param array<int, array{method: string, uri: string, middleware: string[]}> $missingApiMiddleware */
    private function reportMissingApiMiddleware(array $missingApiMiddleware): void
    {
        if (empty($missingApiMiddleware)) {
            return;
        }

        $this->newLine();
        $this->line('<fg=yellow>Routes missing api middleware ('.count($missingApiMiddleware).') — warnings:</>');
        $this->table(
            ['Method', 'URI'],
            array_map(
                fn (array $route): array => [strtoupper($route['method']), '/'.$route['uri']],
                $missingApiMiddleware
            )
        );
    }

    /** @param array<int, array{route: string, issues: string[]}> $incomplete */
    private function reportIncomplete(array $incomplete): void
    {
        if (empty($incomplete)) {
            return;
        }

        $this->newLine();
        $this->line('<fg=yellow>Incomplete annotations ('.count($incomplete).') — warnings:</>');

        foreach ($incomplete as $item) {
            $this->line('  <fg=yellow>'.$item['route'].':</> '.implode(', ', $item['issues']));
        }
    }
}
