<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiVersion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiVersionTest extends TestCase
{
    public function test_resolve_version_from_accept_header(): void
    {
        $request = Request::create('/api/health/app', 'GET');
        $request->headers->set('Accept', 'application/vnd.api.v1+json');

        (new ApiVersion)->handle($request, fn (Request $r): Response => new Response);

        $this->assertSame('1', $request->attributes->get('api.version'));
    }

    public function test_resolve_version_from_x_api_version_header(): void
    {
        $request = Request::create('/api/health/app', 'GET');
        $request->headers->set('X-API-Version', '1');

        (new ApiVersion)->handle($request, fn (Request $r): Response => new Response);

        $this->assertSame('1', $request->attributes->get('api.version'));
    }

    public function test_defaults_to_config_major_version_when_no_header(): void
    {
        $request = Request::create('/api/health/app', 'GET');

        (new ApiVersion)->handle($request, fn (Request $r): Response => new Response);

        $this->assertSame('1', $request->attributes->get('api.version'));
    }

    public function test_returns_406_for_unsupported_version(): void
    {
        $request = Request::create('/api/health/app', 'GET');
        $request->headers->set('X-API-Version', '99');

        $response = (new ApiVersion)->handle($request, fn (Request $r): Response => new Response);

        $this->assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
    }
}
