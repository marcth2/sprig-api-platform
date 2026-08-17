<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use Illuminate\Support\Facades\Redis;

class RedisHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'redis';
    }

    public function check(): HealthStatusData
    {
        $start = hrtime(true);
        try {
            $connection = Redis::connection();

            /** @phpstan-ignore method.notFound */
            $connection->ping();

            /** @var array<string, mixed> $info */
            /** @phpstan-ignore method.notFound */
            $info = $connection->info();

            $redisVersion = isset($info['redis_version']) && is_string($info['redis_version']) ? $info['redis_version'] : 'unknown';
            $usedMemory = isset($info['used_memory']) && (is_string($info['used_memory']) || is_int($info['used_memory'])) ? (string) $info['used_memory'] : 'unknown';
            $connectedClients = isset($info['connected_clients']) && is_numeric($info['connected_clients']) ? (int) $info['connected_clients'] : 0;

            $ms = intdiv(hrtime(true) - $start, 1_000_000);

            return new HealthStatusData('redis', ServiceStatus::Ok, 200, $ms, [
                'version' => $redisVersion,
                'used_memory' => $usedMemory,
                'connected_clients' => $connectedClients,
            ]);
        } catch (\Exception) {
            $ms = intdiv(hrtime(true) - $start, 1_000_000);

            return new HealthStatusData('redis', ServiceStatus::Down, 503, $ms);
        }
    }
}
