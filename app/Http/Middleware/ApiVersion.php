<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersion
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $version = $this->resolveVersion($request);

        /** @var list<string> $supported */
        $supported = config('api.supported_versions', ['1']);

        if (! in_array($version, $supported, strict: true)) {
            return new JsonResponse(
                ['message' => "Unsupported API version '{$version}'. Supported: ".implode(', ', $supported)],
                Response::HTTP_NOT_ACCEPTABLE
            );
        }

        $request->attributes->set('api.version', $version);

        return $next($request);
    }

    private function resolveVersion(Request $request): string
    {
        $xApiVersion = $request->header('X-API-Version');
        if (is_string($xApiVersion) && $xApiVersion !== '') {
            return $xApiVersion;
        }

        $accept = $request->header('Accept', '');
        if (is_string($accept) && preg_match('/application\/vnd\.[^.]+\.v(\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }

        /** @var string $configVersion */
        $configVersion = config('api.version', '1.0.0');

        return (string) explode('.', $configVersion)[0];
    }
}
