<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Sprig API Platform',
    version: L5_SWAGGER_CONST_VERSION,
    description: 'Laravel 13 platform serving as a team starter template and home for migrated services. All endpoints require a Sanctum Bearer token (Authorization: Bearer <token>). API version is negotiated via the X-API-Version request header (current: v1).',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Local development')]
#[OA\Tag(
    name: 'HealthCheck',
    description: 'Service readiness checks for deployment pipelines, monitoring systems, and local debugging. Consumers: CI/CD post-deploy probes (GET /api/health), developer drill-down (GET /api/health/{service}), and CLI (php artisan health:check).',
)]
class ApiController extends Controller {}
