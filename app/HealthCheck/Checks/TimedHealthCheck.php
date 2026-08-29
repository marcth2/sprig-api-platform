<?php

declare(strict_types=1);

namespace App\HealthCheck\Checks;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Data\EmptyHealthMeta;
use App\HealthCheck\Data\HealthProbeResult;
use App\HealthCheck\Data\HealthStatusData;
use App\HealthCheck\Enums\ServiceState;
use Symfony\Component\HttpFoundation\Response;

abstract class TimedHealthCheck implements HealthCheckInterface
{
    final public function check(): HealthStatusData
    {
        $start = hrtime(true);

        try {
            $result = $this->probe();
        } catch (\Exception) {
            $result = new HealthProbeResult(ServiceState::Down, new EmptyHealthMeta);
        }

        $ms = intdiv(hrtime(true) - $start, 1_000_000);
        $status = $result->state === ServiceState::Ok ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new HealthStatusData($this->name(), $result->state, $status, $ms, $result->meta);
    }

    abstract protected function probe(): HealthProbeResult;
}
