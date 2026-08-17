<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

class ForceJsonResponseTest extends TestCase
{
    public function test_api_returns_json_without_accept_header(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(401);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type', ''));
    }

    public function test_api_sets_accept_header_to_json(): void
    {
        $response = $this->get('/api/health');

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type', ''));
    }

    public function test_non_api_route_is_not_forced_to_json(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
        // Web routes should not be forced to JSON
        $this->assertStringNotContainsString('application/json', $response->headers->get('Content-Type', ''));
    }
}
