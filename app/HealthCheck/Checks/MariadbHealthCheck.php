<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;
use Illuminate\Support\Facades\DB;

class MariadbHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'mariadb';
    }

    public function check(): HealthStatusData
    {
        $start = hrtime(true);
        try {
            DB::connection()->getPdo();

            $versionRaw = DB::scalar('SELECT VERSION()');
            $version = is_string($versionRaw) ? $versionRaw : 'unknown';

            /** @var object{Value: mixed}|null $maxRow */
            $maxRow = DB::selectOne("SHOW VARIABLES LIKE 'max_connections'");
            $maxVal = $maxRow !== null && isset($maxRow->Value) ? $maxRow->Value : null;
            $maxConnections = is_numeric($maxVal) ? (int) $maxVal : 0;

            /** @var object{Value: mixed}|null $threadRow */
            $threadRow = DB::selectOne("SHOW STATUS LIKE 'Threads_connected'");
            $threadVal = $threadRow !== null && isset($threadRow->Value) ? $threadRow->Value : null;
            $threadsConnected = is_numeric($threadVal) ? (int) $threadVal : 0;

            $ms = intdiv(hrtime(true) - $start, 1_000_000);

            return new HealthStatusData('mariadb', ServiceStatus::Ok, 200, $ms, [
                'version' => $version,
                'max_connections' => $maxConnections,
                'threads_connected' => $threadsConnected,
            ]);
        } catch (\Exception) {
            $ms = intdiv(hrtime(true) - $start, 1_000_000);

            return new HealthStatusData('mariadb', ServiceStatus::Down, 503, $ms);
        }
    }
}
