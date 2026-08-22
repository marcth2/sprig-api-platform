<?php

declare(strict_types=1);

namespace Tests\Unit\HealthCheck;

use App\HealthCheck\Checks\ApplicationHealthCheck;
use App\HealthCheck\Data\AppHealthMeta;
use App\HealthCheck\Data\PhpIniData;
use App\HealthCheck\Enums\ServiceState;
use Tests\TestCase;

class ApplicationHealthCheckTest extends TestCase
{
    public function test_check_returns_ok_when_healthy(): void
    {
        $result = (new ApplicationHealthCheck)->check();

        $this->assertSame('app', $result->service);
        $this->assertSame(ServiceState::Ok, $result->state);
        $this->assertSame(200, $result->status);
    }

    public function test_check_meta_is_app_health_meta_with_php_ini(): void
    {
        $result = (new ApplicationHealthCheck)->check();

        $this->assertInstanceOf(AppHealthMeta::class, $result->meta);
        $this->assertInstanceOf(PhpIniData::class, $result->meta->phpIni);
        $this->assertSame(ini_get('memory_limit'), $result->meta->phpIni->memoryLimit);
        $this->assertSame(ini_get('max_execution_time'), $result->meta->phpIni->maxExecutionTime);
        $this->assertSame((bool) ini_get('opcache.enable'), $result->meta->phpIni->opcacheEnabled);
    }

    public function test_check_meta_app_version_matches_config(): void
    {
        $result = (new ApplicationHealthCheck)->check();

        $this->assertSame((string) config('app.version'), $result->meta->appVersion);
    }

    public function test_check_returns_degraded_when_in_maintenance_mode(): void
    {
        $downFile = storage_path('framework/down');
        file_put_contents($downFile, json_encode([
            'secret' => null,
            'message' => 'test',
            'status' => 503,
            'template' => null,
        ]));

        try {
            $result = (new ApplicationHealthCheck)->check();

            $this->assertSame(ServiceState::Degraded, $result->state);
            $this->assertSame(503, $result->status);
            $this->assertContains('maintenance_mode', $result->meta->degradedReasons);
        } finally {
            if (file_exists($downFile)) {
                unlink($downFile);
            }
        }
    }

    public function test_check_returns_degraded_when_debug_enabled_in_non_local_env(): void
    {
        // APP_ENV=testing (not local), APP_DEBUG=false by default in tests;
        // temporarily enable debug to trigger the degraded check
        config(['app.debug' => true]);

        $result = (new ApplicationHealthCheck)->check();

        $this->assertContains('debug_enabled', $result->meta->degradedReasons);
        $this->assertSame(ServiceState::Degraded, $result->state);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_check_does_not_degrade_debug_or_opcache_in_local_env(): void
    {
        // isLocal() guards debug_enabled/opcache_disabled from firing in local dev,
        // where both are commonly true/off by default. Never exercised before: every
        // other test here runs under APP_ENV=testing, so this guard's true-branch
        // (isLocal() === true) had 100% line coverage but zero real execution.
        config(['app.debug' => true]);
        app()->instance('env', 'local');
        ini_set('opcache.enable', '0');

        $result = (new ApplicationHealthCheck)->check();

        $this->assertNotContains('debug_enabled', $result->meta->degradedReasons);
        $this->assertNotContains('opcache_disabled', $result->meta->degradedReasons);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_check_returns_degraded_when_opcache_disabled_in_non_local_env(): void
    {
        // Runs in a separate process so that ini_set cannot bleed into other tests.
        // OPcache can be disabled at runtime but cannot be re-enabled in the same process.
        ini_set('opcache.enable', '0');

        $result = (new ApplicationHealthCheck)->check();

        $this->assertContains('opcache_disabled', $result->meta->degradedReasons);
    }

    public function test_check_returns_degraded_when_memory_limit_low(): void
    {
        $original = ini_get('memory_limit');
        ini_set('memory_limit', '64M');

        try {
            $result = (new ApplicationHealthCheck)->check();

            $this->assertContains('memory_limit_low', $result->meta->degradedReasons);
        } finally {
            ini_set('memory_limit', $original !== false ? $original : '128M');
        }
    }

    public function test_check_handles_unlimited_memory_limit(): void
    {
        $original = ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        try {
            $result = (new ApplicationHealthCheck)->check();

            // Unlimited memory (-1) should never add memory_limit_low
            $this->assertNotContains('memory_limit_low', $result->meta->degradedReasons);
            $this->assertSame('app', $result->service);
        } finally {
            ini_set('memory_limit', $original !== false ? $original : '128M');
        }
    }

    public function test_check_handles_gigabyte_memory_limit(): void
    {
        $original = ini_get('memory_limit');
        ini_set('memory_limit', '2G');

        try {
            $result = (new ApplicationHealthCheck)->check();

            // 2G = 2048MB — well above MIN_MEMORY_LIMIT_MB
            $this->assertNotContains('memory_limit_low', $result->meta->degradedReasons);
            $this->assertSame('app', $result->service);
        } finally {
            ini_set('memory_limit', $original !== false ? $original : '128M');
        }
    }
}
