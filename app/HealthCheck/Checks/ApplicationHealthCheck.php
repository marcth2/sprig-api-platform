<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Data\AppHealthMeta;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceStatus;

class ApplicationHealthCheck implements HealthCheckInterface
{
    private const int MIN_MEMORY_LIMIT_MB = 128;

    public function name(): string
    {
        return 'app';
    }

    public function check(): HealthStatusData
    {
        $start = hrtime(true);

        /** @var list<string> $degraded */
        $degraded = [];

        if (app()->isDownForMaintenance()) {
            $degraded[] = 'maintenance_mode';
        }

        if ((bool) config('app.debug') && ! app()->isLocal()) {
            $degraded[] = 'debug_enabled';
        }

        $opcacheEnabled = ini_get('opcache.enable');
        if (($opcacheEnabled === false || $opcacheEnabled === '' || $opcacheEnabled === '0') && ! app()->isLocal()) {
            $degraded[] = 'opcache_disabled';
        }

        $limitMb = $this->parseMemoryLimitMb(ini_get('memory_limit'));
        if ($limitMb !== null && $limitMb < self::MIN_MEMORY_LIMIT_MB) {
            $degraded[] = 'memory_limit_low';
        }

        $status = empty($degraded) ? ServiceStatus::Ok : ServiceStatus::Degraded;
        $code = $status === ServiceStatus::Ok ? 200 : 503;
        $ms = intdiv(hrtime(true) - $start, 1_000_000);

        $appVersion = config('app.version');
        $appVersion = is_string($appVersion) ? $appVersion : '0.0.0-unversioned';

        $postMaxSize = ini_get('post_max_size');
        $postMaxSize = is_string($postMaxSize) ? $postMaxSize : '';

        return new HealthStatusData('app', $status, $code, $ms, new AppHealthMeta(
            appVersion: $appVersion,
            phpVersion: PHP_VERSION,
            frameworkVersion: app()->version(),
            environment: app()->environment(),
            maintenanceMode: app()->isDownForMaintenance(),
            degradedReasons: $degraded,
            phpIni: [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'post_max_size' => $postMaxSize,
                'opcache_enabled' => (bool) ini_get('opcache.enable'),
            ],
        ));
    }

    private function parseMemoryLimitMb(string $memoryLimit): ?int
    {
        if ($memoryLimit === '-1') {
            return null;
        }

        $value = (int) $memoryLimit;
        $suffix = strtolower(substr($memoryLimit, -1));

        return match ($suffix) {
            'g' => $value * 1024,
            'm' => $value,
            default => intdiv($value, 1024 * 1024),
        };
    }
}
