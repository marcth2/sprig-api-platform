<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\MariadbHealthCheck;
use App\HealthCheck\Data\MariadbHealthMeta;
use App\HealthCheck\Enums\ServiceState;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MariadbHealthCheckTest extends TestCase
{
    public function test_name_returns_mariadb(): void
    {
        $this->assertSame('mariadb', (new MariadbHealthCheck)->name());
    }

    public function test_check_returns_ok_when_connected(): void
    {
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo');
        $connection->shouldReceive('scalar')->with('SELECT VERSION()')->andReturn('10.11.0-MariaDB');
        $connection->shouldReceive('selectOne')
            ->with("SHOW VARIABLES LIKE 'max_connections'")
            ->andReturn((object) ['Value' => '100']);
        $connection->shouldReceive('selectOne')
            ->with("SHOW STATUS LIKE 'Threads_connected'")
            ->andReturn((object) ['Value' => '3']);

        DB::shouldReceive('connection')->with('mariadb')->andReturn($connection);

        $result = (new MariadbHealthCheck)->check();

        $this->assertSame(ServiceState::Ok, $result->state);
        $this->assertSame('mariadb', $result->service);
        $this->assertInstanceOf(MariadbHealthMeta::class, $result->meta);

        /** @var MariadbHealthMeta $meta */
        $meta = $result->meta;
        $this->assertSame('10.11.0-MariaDB', $meta->version);
        $this->assertSame(100, $meta->maxConnections);
        $this->assertSame(3, $meta->threadsConnected);
    }

    public function test_check_against_real_mariadb_returns_ok(): void
    {
        $result = (new MariadbHealthCheck)->check();

        $this->assertSame('mariadb', $result->service);
        $this->assertSame(ServiceState::Ok, $result->state);
        $this->assertSame(200, $result->status);
        $this->assertInstanceOf(MariadbHealthMeta::class, $result->meta);

        /** @var MariadbHealthMeta $meta */
        $meta = $result->meta;
        $this->assertStringContainsString('MariaDB', $meta->version);
        $this->assertGreaterThan(0, $meta->maxConnections);
        $this->assertGreaterThanOrEqual(1, $meta->threadsConnected);
    }
}
