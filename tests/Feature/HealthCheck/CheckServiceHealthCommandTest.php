<?php

declare(strict_types=1);

namespace Tests\Feature\HealthCheck;

use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use Tests\TestCase;

class CheckServiceHealthCommandTest extends TestCase
{
    public function test_health_check_command_displays_all_services(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkAll')->once()->andReturn([
                new HealthStatusData('laravel', ServiceStatus::Ok, 200, 1, []),
                new HealthStatusData('mariadb', ServiceStatus::Ok, 200, 2, []),
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 3, []),
            ]);
        });

        $this->artisan('health:check')->assertExitCode(0);
    }

    public function test_health_check_command_displays_single_service(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('redis')->once()->andReturn(
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 2, [])
            );
        });

        $this->artisan('health:check redis')->assertExitCode(0);
    }

    public function test_health_check_command_displays_meta_for_single_service(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('app')->once()->andReturn(
                new HealthStatusData('app', ServiceStatus::Ok, 200, 1, [
                    'api_version' => config('api.version'),
                    'environment' => 'local',
                    'php_ini' => ['opcache_enabled' => true],
                ])
            );
        });

        $this->artisan('health:check app')
            ->expectsOutputToContain('api_version')
            ->expectsOutputToContain('php_ini.opcache_enabled')
            ->assertExitCode(0);
    }

    public function test_health_check_command_omits_meta_table_when_meta_is_empty(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('redis')->once()->andReturn(
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 2, [])
            );
        });

        $this->artisan('health:check redis')
            ->doesntExpectOutputToContain('Key')
            ->assertExitCode(0);
    }

    public function test_health_check_command_errors_on_unknown_service(): void
    {
        $this->artisan('health:check unknown-service')
            ->expectsOutputToContain('Unknown service')
            ->assertExitCode(1);
    }

    public function test_health_check_command_returns_failure_when_aggregate_is_unhealthy(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkAll')->once()->andReturn([
                new HealthStatusData('laravel', ServiceStatus::Ok, 200, 1, []),
                new HealthStatusData('mariadb', ServiceStatus::Down, 503, 2, []),
                new HealthStatusData('redis', ServiceStatus::Ok, 200, 3, []),
            ]);
        });

        $this->artisan('health:check')->assertExitCode(1);
    }

    public function test_health_check_command_returns_failure_when_single_service_is_down(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('mariadb')->once()->andReturn(
                new HealthStatusData('mariadb', ServiceStatus::Down, 503, 2, [])
            );
        });

        $this->artisan('health:check mariadb')->assertExitCode(1);
    }

    public function test_health_check_command_returns_failure_when_single_service_is_degraded(): void
    {
        $this->mock(HealthCheckerService::class, function ($mock): void {
            $mock->shouldReceive('checkOne')->with('app')->once()->andReturn(
                new HealthStatusData('app', ServiceStatus::Degraded, 200, 1, [])
            );
        });

        $this->artisan('health:check app')->assertExitCode(1);
    }
}
