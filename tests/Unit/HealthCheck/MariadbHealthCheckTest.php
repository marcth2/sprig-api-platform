<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\MariadbHealthCheck;
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
        $this->assertSame('10.11.0-MariaDB', $result->meta->version);
        $this->assertSame(100, $result->meta->maxConnections);
        $this->assertSame(3, $result->meta->threadsConnected);
    }

    public function test_check_returns_down_when_connection_fails(): void
    {
        DB::shouldReceive('connection')->with('mariadb')->andThrow(new \Exception('Connection refused'));

        $result = (new MariadbHealthCheck)->check();

        $this->assertSame('mariadb', $result->service);
        $this->assertSame(ServiceState::Down, $result->state);
        $this->assertSame(503, $result->status);
    }

    public function test_check_against_real_mariadb_returns_ok(): void
    {
        $result = (new MariadbHealthCheck)->check();

        $this->assertSame('mariadb', $result->service);
        $this->assertSame(ServiceState::Ok, $result->state);
        $this->assertSame(200, $result->status);
        $this->assertStringContainsString('MariaDB', $result->meta->version);
        $this->assertGreaterThan(0, $result->meta->maxConnections);
        $this->assertGreaterThanOrEqual(1, $result->meta->threadsConnected);
    }
}
