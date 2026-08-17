<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_x_api_version_header_is_used_as_version(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('app')->andReturn(
                new HealthStatusData('app', ServiceStatus::Ok, 200, 1, [])
            );
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/app', ['X-API-Version' => '1']);

        $response->assertStatus(200);
    }

    public function test_accept_header_version_negotiation_is_used(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('app')->andReturn(
                new HealthStatusData('app', ServiceStatus::Ok, 200, 1, [])
            );
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/health/app', ['Accept' => 'application/vnd.api.v1+json']);

        $response->assertStatus(200);
    }

    public function test_unsupported_version_returns_406(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/app', ['X-API-Version' => '99'])
            ->assertStatus(406)
            ->assertJsonFragment(['message' => "Unsupported API version '99'. Supported: 1"]);
    }

    public function test_defaults_to_version_1_when_no_header(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('app')->andReturn(
                new HealthStatusData('app', ServiceStatus::Ok, 200, 1, [])
            );
        });

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/app')
            ->assertStatus(200);
    }
}
