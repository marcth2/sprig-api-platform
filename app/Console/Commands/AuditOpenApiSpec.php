<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Yaml\Yaml;

class AuditOpenApiSpec extends Command
{
    protected $signature = 'l5-swagger:audit
        {--fail-on-warnings : Exit non-zero when warnings (incomplete annotations) are found}
        {--spec-file= : Path to OpenAPI spec file (defaults to configured l5-swagger output)}';

    protected $description = 'Audit OpenAPI spec — detect undocumented routes, phantom paths, and incomplete annotations';

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
        $routes = $this->getAuditableRoutes();

        $undocumented = $this->findUndocumented($routes, $specPaths);
        $phantom = $this->findPhantom($routes, $specPaths);
        $incomplete = $this->findIncomplete($routes, $specPaths);

        $this->reportUndocumented($undocumented);
        $this->reportPhantom($phantom);
        $this->reportIncomplete($incomplete);

        $hasErrors = count($undocumented) > 0 || count($phantom) > 0;
        $hasWarnings = count($incomplete) > 0;

        if ($hasErrors) {
            $total = count($undocumented) + count($phantom);
            $this->newLine();
            $this->error("Audit failed: {$total} error(s).");

            return self::FAILURE;
        }

        if ($hasWarnings && $this->option('fail-on-warnings')) {
            $this->newLine();
            $this->error('Audit failed: '.count($incomplete).' warning(s) found (--fail-on-warnings).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Check', 'Result'],
            [
                ['Routes audited', (string) count($routes)],
                ['Spec paths', (string) count($specPaths)],
                ['Undocumented routes', '0'],
                ['Phantom spec paths', '0'],
                ['Incomplete annotations', $hasWarnings ? count($incomplete).' warning(s)' : '0'],
            ]
        );

        $warningNote = $hasWarnings ? ' ('.count($incomplete).' warning(s))' : '';
        $this->info("Audit passed{$warningNote}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $spec
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
     * @return array<int, array{method: string, uri: string, middleware: string[]}>
     */
    private function getAuditableRoutes(): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            /** @var string[] $middleware */
            $middleware = $route->middleware();

            if ($this->isL5SwaggerRoute($middleware) || ! in_array('api', $middleware)) {
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

    /** @param string[] $middleware */
    private function isL5SwaggerRoute(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (str_contains($m, 'L5Swagger')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{method: string, uri: string, middleware: string[]}>  $routes
     * @param  array<int, array{method: string, path: string, operation: array<string, mixed>}>  $specPaths
     * @return array<int, array{method: string, uri: string, middleware: string[]}>
     */
    private function findUndocumented(array $routes, array $specPaths): array
    {
        $specSignatures = array_map(fn (array $p): string => $p['method'].':'.$p['path'], $specPaths);

        return array_values(array_filter(
            $routes,
            fn (array $r): bool => ! in_array($r['method'].':'.$r['uri'], $specSignatures)
        ));
    }

    /**
     * Only api/ prefixed paths are checked — non-api paths (e.g. /up) are excluded from phantom detection.
     *
     * @param  array<int, array{method: string, uri: string, middleware: string[]}>  $routes
     * @param  array<int, array{method: string, path: string, operation: array<string, mixed>}>  $specPaths
     * @return array<int, array{method: string, path: string, operation: array<string, mixed>}>
     */
    private function findPhantom(array $routes, array $specPaths): array
    {
        $routeSignatures = array_map(fn (array $r): string => $r['method'].':'.$r['uri'], $routes);

        return array_values(array_filter(
            $specPaths,
            fn (array $p): bool => str_starts_with($p['path'], 'api/')
                && ! in_array($p['method'].':'.$p['path'], $routeSignatures)
        ));
    }

    /**
     * @param  array<int, array{method: string, uri: string, middleware: string[]}>  $routes
     * @param  array<int, array{method: string, path: string, operation: array<string, mixed>}>  $specPaths
     * @return array<int, array{route: string, issues: string[]}>
     */
    private function findIncomplete(array $routes, array $specPaths): array
    {
        $specBySignature = [];
        foreach ($specPaths as $path) {
            $specBySignature[$path['method'].':'.$path['path']] = $path['operation'];
        }

        $issues = [];

        foreach ($routes as $route) {
            $sig = $route['method'].':'.$route['uri'];

            if (! isset($specBySignature[$sig])) {
                continue;
            }

            $operation = $specBySignature[$sig];
            $routeIssues = [];

            if (empty($operation['operationId'])) {
                $routeIssues[] = 'missing operationId';
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

    /** @param string[] $middleware */
    private function routeHasSanctum(array $middleware): bool
    {
        return (bool) array_filter(
            $middleware,
            fn (string $m): bool => str_contains($m, 'sanctum') || str_contains($m, 'Authenticate')
        );
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
            array_map(fn (array $r): array => [strtoupper($r['method']), '/'.$r['uri']], $undocumented)
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
            array_map(fn (array $p): array => [strtoupper($p['method']), '/'.$p['path']], $phantom)
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
