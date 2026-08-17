<?php

declare(strict_types=1);

namespace App\HealthCheck\Services;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Data\HealthStatusData;

class HealthCheckerService
{
    /** @param iterable<HealthCheckInterface> $checkers */
    public function __construct(private readonly iterable $checkers) {}

    /** @return HealthStatusData[] */
    public function checkAll(): array
    {
        $results = [];
        foreach ($this->checkers as $checker) {
            $results[] = $checker->check();
        }

        return $results;
    }

    public function checkOne(string $name): HealthStatusData
    {
        foreach ($this->checkers as $checker) {
            if ($checker->name() === $name) {
                return $checker->check();
            }
        }
        throw new \InvalidArgumentException("Unknown service: {$name}");
    }
}
