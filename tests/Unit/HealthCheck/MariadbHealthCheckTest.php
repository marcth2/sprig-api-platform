<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\MariadbHealthCheck;
use App\HealthCheck\Enums\ServiceStatus;
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

        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('scalar')->with('SELECT VERSION()')->andReturn('10.11.0-MariaDB');
        DB::shouldReceive('selectOne')
            ->with("SHOW VARIABLES LIKE 'max_connections'")
            ->andReturn((object) ['Value' => '100']);
        DB::shouldReceive('selectOne')
            ->with("SHOW STATUS LIKE 'Threads_connected'")
            ->andReturn((object) ['Value' => '3']);

        $result = (new MariadbHealthCheck)->check();

        $this->assertSame(ServiceStatus::Ok, $result->status);
        $this->assertSame('mariadb', $result->service);
        $this->assertSame('10.11.0-MariaDB', $result->meta['version']);
        $this->assertSame(100, $result->meta['max_connections']);
        $this->assertSame(3, $result->meta['threads_connected']);
    }

    public function test_check_returns_down_when_connection_fails(): void
    {
        DB::shouldReceive('connection')->andThrow(new \Exception('Connection refused'));

        $result = (new MariadbHealthCheck)->check();

        $this->assertSame('mariadb', $result->service);
        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame(503, $result->code);
    }
}
