<?php

declare(strict_types=1);

/**
 * Console routes — Artisan command registration.
 *
 * Actions registered here are automatically wired as Artisan commands.
 *
 * @see https://laravelactions.com
 * @see docs/architecture/README.md
 */

use App\HealthCheck\Actions\CheckServiceHealth;
use Lorisleiva\Actions\Facades\Actions;

Actions::registerCommandsForAction(CheckServiceHealth::class);
