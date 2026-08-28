<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\TimedHealthCheck;
use App\HealthCheck\Data\EmptyHealthMeta;
use App\HealthCheck\Data\HealthProbeResult;
use App\HealthCheck\Enums\ServiceState;
use Tests\TestCase;

class TimedHealthCheckTest extends TestCase
{
    public function test_check_returns_ok_when_probe_succeeds(): void
    {
        $check = new class extends TimedHealthCheck
        {
            public function name(): string
            {
                return 'stub';
            }

            protected function probe(): HealthProbeResult
            {
                return new HealthProbeResult;
            }
        };

        $result = $check->check();

        $this->assertSame('stub', $result->service);
        $this->assertSame(ServiceState::Ok, $result->state);
        $this->assertSame(200, $result->status);
        $this->assertInstanceOf(EmptyHealthMeta::class, $result->meta);
    }

    public function test_check_returns_down_when_probe_throws(): void
    {
        $check = new class extends TimedHealthCheck
        {
            public function name(): string
            {
                return 'stub';
            }

            protected function probe(): HealthProbeResult
            {
                throw new \Exception('probe failed');
            }
        };

        $result = $check->check();

        $this->assertSame('stub', $result->service);
        $this->assertSame(ServiceState::Down, $result->state);
        $this->assertSame(503, $result->status);
        $this->assertInstanceOf(EmptyHealthMeta::class, $result->meta);
    }

    public function test_check_returns_degraded_status_when_probe_reports_degraded(): void
    {
        $check = new class extends TimedHealthCheck
        {
            public function name(): string
            {
                return 'stub';
            }

            protected function probe(): HealthProbeResult
            {
                return new HealthProbeResult(ServiceState::Degraded, new EmptyHealthMeta);
            }
        };

        $result = $check->check();

        $this->assertSame(ServiceState::Degraded, $result->state);
        $this->assertSame(503, $result->status);
    }
}
