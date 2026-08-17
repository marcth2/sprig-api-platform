<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\RedisHealthCheck;
use App\HealthCheck\Enums\ServiceStatus;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisHealthCheckTest extends TestCase
{
    public function test_name_returns_redis(): void
    {
        $this->assertSame('redis', (new RedisHealthCheck)->name());
    }

    public function test_check_returns_ok_when_connected(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'redis_version' => '7.0.0',
            'used_memory' => '1000',
            'connected_clients' => 2,
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = (new RedisHealthCheck)->check();

        $this->assertSame(ServiceStatus::Ok, $result->status);
        $this->assertSame('redis', $result->service);
        $this->assertSame('7.0.0', $result->meta['version']);
        $this->assertSame('1000', $result->meta['used_memory']);
        $this->assertSame(2, $result->meta['connected_clients']);
    }

    public function test_check_returns_down_when_connection_fails(): void
    {
        Redis::shouldReceive('connection')->andThrow(new \Exception('Connection refused'));

        $result = (new RedisHealthCheck)->check();

        $this->assertSame('redis', $result->service);
        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame(503, $result->code);
    }
}
