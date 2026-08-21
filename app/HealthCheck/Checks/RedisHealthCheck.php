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
            $connection = Redis::connection('default');

            $connection->ping();

            /** @var array<string, mixed> $info */
            $info = $connection->info();

            $hasVersion = isset($info['redis_version']) && is_string($info['redis_version']);
            $redisVersion = $hasVersion ? $info['redis_version'] : 'unknown';

            $hasMemory = is_string($info['used_memory'] ?? null) || is_int($info['used_memory'] ?? null);
            $usedMemory = $hasMemory ? (string) $info['used_memory'] : 'unknown';

            $hasClients = isset($info['connected_clients']) && is_numeric($info['connected_clients']);
            $connectedClients = $hasClients ? (int) $info['connected_clients'] : 0;

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
