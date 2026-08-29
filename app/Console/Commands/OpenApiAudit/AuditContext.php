<?php

declare(strict_types=1);

namespace App\Console\Commands\OpenApiAudit;

use Illuminate\Support\Facades\Route as RouteFacade;
use OpenApi\Attributes as OA;
use ReflectionClass;
use Spatie\LaravelData\Data;
use Symfony\Component\Finder\Finder;

final class AuditContext
{
    /**
     * @param array<int, array{method: string, path: string, operation: array<string, mixed>}> $specPaths
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $allRoutes
     * @param array<int, array{method: string, uri: string, middleware: string[]}> $routes
     * @param array<int, ReflectionClass<Data>> $dtoClasses
     */
    public function __construct(
        public readonly array $specPaths,
        public readonly array $allRoutes,
        public readonly array $routes,
        public readonly array $dtoClasses,
    ) {}

    /** @param array<string, mixed> $spec */
    public static function fromCommandOptions(array $spec, string $dtoDir, string $dtoNamespace): self
    {
        $specPaths = self::extractSpecPaths($spec);
        $allRoutes = self::getAllRoutes();
        $routes = self::filterApiRoutes($allRoutes);
        $dtoClasses = self::getSchemaDtoClasses($dtoDir, $dtoNamespace);

        return new self($specPaths, $allRoutes, $routes, $dtoClasses);
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<int, array{method: string, path: string, operation: array<string, mixed>}>
     */
    private static function extractSpecPaths(array $spec): array
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
    private static function getAllRoutes(): array
    {
        $routes = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var string[] $middleware */
            $middleware = $route->middleware();

            if (self::isL5SwaggerRoute($middleware)) {
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
    private static function filterApiRoutes(array $routes): array
    {
        return array_values(array_filter(
            $routes,
            fn (array $route): bool => in_array('api', $route['middleware'])
        ));
    }

    /** @param string[] $middleware */
    private static function isL5SwaggerRoute(array $middleware): bool
    {
        foreach ($middleware as $middlewareEntry) {
            if (str_contains($middlewareEntry, 'L5Swagger')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, ReflectionClass<Data>> */
    private static function getSchemaDtoClasses(string $dtoDir, string $dtoNamespace): array
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
}
