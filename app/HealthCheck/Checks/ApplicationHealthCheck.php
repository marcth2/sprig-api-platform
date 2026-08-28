<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Data\AppHealthMeta;
use App\HealthCheck\Data\HealthProbeResult;
use App\HealthCheck\Data\PhpIniData;
use App\HealthCheck\Enums\ServiceState;

class ApplicationHealthCheck extends TimedHealthCheck
{
    private const int MIN_MEMORY_LIMIT_MB = 128;

    public function name(): string
    {
        return 'app';
    }

    protected function probe(): HealthProbeResult
    {
        /** @var list<string> $degraded */
        $degraded = [];

        if (app()->isDownForMaintenance()) {
            $degraded[] = 'maintenance_mode';
        }

        if ((bool) config('app.debug') && ! app()->isLocal()) {
            $degraded[] = 'debug_enabled';
        }

        $opcacheEnabled = (bool) ini_get('opcache.enable');
        if (! $opcacheEnabled && ! app()->isLocal()) {
            $degraded[] = 'opcache_disabled';
        }

        $limitMb = $this->parseMemoryLimitMb(ini_get('memory_limit'));
        if ($limitMb !== null && $limitMb < self::MIN_MEMORY_LIMIT_MB) {
            $degraded[] = 'memory_limit_low';
        }

        $state = empty($degraded) ? ServiceState::Ok : ServiceState::Degraded;

        $appVersion = config('app.version');
        $appVersion = is_string($appVersion) ? $appVersion : '0.0.0-unversioned';

        $postMaxSize = ini_get('post_max_size');
        $postMaxSize = is_string($postMaxSize) ? $postMaxSize : '';

        return new HealthProbeResult($state, new AppHealthMeta(
            appVersion: $appVersion,
            phpVersion: PHP_VERSION,
            frameworkVersion: app()->version(),
            environment: app()->environment(),
            maintenanceMode: app()->isDownForMaintenance(),
            degradedReasons: $degraded,
            phpIni: new PhpIniData(
                memoryLimit: ini_get('memory_limit'),
                maxExecutionTime: ini_get('max_execution_time'),
                postMaxSize: $postMaxSize,
                opcacheEnabled: $opcacheEnabled,
            ),
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
