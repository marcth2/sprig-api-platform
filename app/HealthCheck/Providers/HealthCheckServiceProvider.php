<?php

declare(strict_types=1);

namespace App\HealthCheck\Providers;

use App\HealthCheck\Contracts\HealthCheckInterface;
use App\HealthCheck\Services\HealthCheckerService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var array<class-string<HealthCheckInterface>> $checks */
        $checks = config('health-check.checks', []);
        $this->app->tag($checks, 'health-checks');

        $this->app->bind(
            HealthCheckerService::class,
            function (Application $app): HealthCheckerService {
                /** @var iterable<HealthCheckInterface> $checkers */
                $checkers = $app->tagged('health-checks');

                return new HealthCheckerService($checkers);
            }
        );
    }
}
