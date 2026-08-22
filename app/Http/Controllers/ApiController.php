<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Sprig API Platform',
    version: L5_SWAGGER_CONST_VERSION,
    description: <<<'TEXT'
        Laravel 13 platform serving as a team starter template and home for migrated services.
        All endpoints require a Sanctum Bearer token (Authorization: Bearer <token>).
        API version is negotiated via the X-API-Version request header (current: v0).
        TEXT,
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Local development')]
#[OA\Tag(
    name: 'HealthCheck',
    description: <<<'TEXT'
        Service readiness checks for monitoring systems and local debugging today; deployment-pipeline
        post-deploy probes (GET /api/health) are a planned consumer with no token-provisioning path yet.
        Consumers: developer drill-down (GET /api/health/{service}) and CLI (php artisan health:check).
        TEXT,
)]
class ApiController extends Controller {}
