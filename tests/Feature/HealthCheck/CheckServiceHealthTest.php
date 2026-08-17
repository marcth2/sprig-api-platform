<?php

declare(strict_types=1);

namespace Tests\Feature\HealthCheck;

use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckServiceHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_framework_up_route_untouched(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    public function test_health_aggregate_returns_200_when_all_healthy(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkAll')->andReturn([
                new HealthStatusData('mariadb', ServiceStatus::Ok, 200, 1, ['latency_ms' => 1]),
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 2, ['latency_ms' => 2]),
            ]);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['services', 'healthy', 'checked_at'])
            ->assertJsonPath('healthy', true);
    }

    public function test_health_aggregate_returns_503_when_service_down(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkAll')->andReturn([
                new HealthStatusData('app', ServiceStatus::Ok, 200, 1, []),
                new HealthStatusData('mariadb', ServiceStatus::Down, 503, 2001, []),
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 2, []),
            ]);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('healthy', false);
    }

    public function test_single_service_check_redis(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health/redis');

        $response->assertStatus(200)
            ->assertJsonPath('service', 'redis');
    }

    public function test_single_service_check_mariadb(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('mariadb')->andReturn(
                new HealthStatusData('mariadb', ServiceStatus::Ok, 200, 1, [])
            );
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health/mariadb');

        $response->assertStatus(200)
            ->assertJsonPath('service', 'mariadb');
    }

    public function test_unknown_service_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health/unknown');

        $response->assertStatus(404);
    }

    public function test_health_requires_auth(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(401);
    }

    public function test_health_service_requires_auth(): void
    {
        $response = $this->getJson('/api/health/redis');

        $response->assertStatus(401);
    }

    public function test_aggregate_response_has_correct_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health');

        $response->assertJsonStructure([
            'services' => [
                '*' => ['service', 'status', 'code', 'execution_time_ms', 'meta'],
            ],
            'healthy',
            'checked_at',
        ]);
    }

    public function test_app_service_check_returns_ok(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('app')->andReturn(
                new HealthStatusData('app', ServiceStatus::Ok, 200, 1, [
                    'api_version' => config('api.version'),
                    'php_version' => PHP_VERSION,
                    'framework_version' => app()->version(),
                    'environment' => 'testing',
                ])
            );
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/health/app');

        $response->assertStatus(200)
            ->assertJsonPath('service', 'app')
            ->assertJsonStructure([
                'meta' => ['api_version', 'php_version', 'environment'],
            ]);
    }
}
