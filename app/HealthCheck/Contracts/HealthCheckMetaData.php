<?php

declare(strict_types=1);

namespace App\HealthCheck\Contracts;

use Spatie\LaravelData\Contracts\TransformableData;

/**
 * Marker interface for a health checker's service-specific `meta` payload.
 *
 * Implementations are Spatie Data classes so each checker's meta shape is
 * typed and documented, while HealthStatusResource's `meta` property stays
 * a generic object in the OpenAPI contract. Extends TransformableData so
 * CheckServiceHealth's CLI table rendering can call toArray() on it.
 */
interface HealthCheckMetaData extends TransformableData {}
