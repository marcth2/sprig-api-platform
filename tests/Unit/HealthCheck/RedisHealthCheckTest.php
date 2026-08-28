<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\RedisHealthCheck;
use App\HealthCheck\Data\RedisHealthMeta;
use App\HealthCheck\Enums\ServiceState;
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

        Redis::shouldReceive('connection')->with('default')->andReturn($connection);

        $result = (new RedisHealthCheck)->check();

        $this->assertSame(ServiceState::Ok, $result->state);
        $this->assertSame('redis', $result->service);
        $this->assertInstanceOf(RedisHealthMeta::class, $result->meta);

        /** @var RedisHealthMeta $meta */
        $meta = $result->meta;
        $this->assertSame('7.0.0', $meta->version);
        $this->assertSame('1000', $meta->usedMemory);
        $this->assertSame(2, $meta->connectedClients);
    }
}
