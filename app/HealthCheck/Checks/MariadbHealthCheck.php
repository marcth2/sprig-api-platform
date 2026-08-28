<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Data\HealthProbeResult;
use App\HealthCheck\Data\MariadbHealthMeta;
use Illuminate\Support\Facades\DB;

class MariadbHealthCheck extends TimedHealthCheck
{
    public function name(): string
    {
        return 'mariadb';
    }

    protected function probe(): HealthProbeResult
    {
        $connection = DB::connection('mariadb');
        $connection->getPdo();

        $versionRaw = $connection->scalar('SELECT VERSION()');
        $version = is_string($versionRaw) ? $versionRaw : 'unknown';

        /** @var object{Value: mixed}|null $maxRow */
        $maxRow = $connection->selectOne("SHOW VARIABLES LIKE 'max_connections'");
        $maxVal = $maxRow !== null && isset($maxRow->Value) ? $maxRow->Value : null;
        $maxConnections = is_numeric($maxVal) ? (int) $maxVal : 0;

        /** @var object{Value: mixed}|null $threadRow */
        $threadRow = $connection->selectOne("SHOW STATUS LIKE 'Threads_connected'");
        $threadVal = $threadRow !== null && isset($threadRow->Value) ? $threadRow->Value : null;
        $threadsConnected = is_numeric($threadVal) ? (int) $threadVal : 0;

        return new HealthProbeResult(meta: new MariadbHealthMeta(
            version: $version,
            maxConnections: $maxConnections,
            threadsConnected: $threadsConnected,
        ));
    }
}
