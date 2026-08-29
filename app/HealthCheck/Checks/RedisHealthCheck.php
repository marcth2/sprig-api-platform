<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Data\HealthProbeResult;
use App\HealthCheck\Data\RedisHealthMeta;
use Illuminate\Support\Facades\Redis;

class RedisHealthCheck extends TimedHealthCheck
{
    public function name(): string
    {
        return 'redis';
    }

    protected function probe(): HealthProbeResult
    {
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

        return new HealthProbeResult(meta: new RedisHealthMeta(
            version: $redisVersion,
            usedMemory: $usedMemory,
            connectedClients: $connectedClients,
        ));
    }
}
