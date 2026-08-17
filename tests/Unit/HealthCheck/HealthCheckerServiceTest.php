<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use App\HealthCheck\Services\HealthCheckerService;
use Tests\TestCase;

class HealthCheckerServiceTest extends TestCase
{
    public function test_check_all_returns_results_from_all_checkers(): void
    {
        $result1 = new HealthStatusData('foo', ServiceStatus::Ok, 200, 1, []);
        $result2 = new HealthStatusData('bar', ServiceStatus::Ok, 200, 2, []);

        $checker1 = \Mockery::mock(HealthCheckInterface::class);
        $checker1->shouldReceive('name')->andReturn('foo');
        $checker1->shouldReceive('check')->andReturn($result1);

        $checker2 = \Mockery::mock(HealthCheckInterface::class);
        $checker2->shouldReceive('name')->andReturn('bar');
        $checker2->shouldReceive('check')->andReturn($result2);

        $service = new HealthCheckerService([$checker1, $checker2]);

        $results = $service->checkAll();

        $this->assertCount(2, $results);
        $this->assertSame($result1, $results[0]);
        $this->assertSame($result2, $results[1]);
    }

    public function test_check_one_returns_result_for_matching_checker(): void
    {
        $expectedResult = new HealthStatusData('foo', ServiceStatus::Ok, 200, 1, []);

        $checker = \Mockery::mock(HealthCheckInterface::class);
        $checker->shouldReceive('name')->andReturn('foo');
        $checker->shouldReceive('check')->andReturn($expectedResult);

        $service = new HealthCheckerService([$checker]);

        $result = $service->checkOne('foo');

        $this->assertSame($expectedResult, $result);
    }

    public function test_check_one_throws_for_unknown_service(): void
    {
        $service = new HealthCheckerService([]);

        $this->expectException(\InvalidArgumentException::class);

        $service->checkOne('anything');
    }
}
