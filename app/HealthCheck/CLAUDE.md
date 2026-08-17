# HealthCheck Domain

## Purpose
Service readiness checks for DevOps/CI/CD pipelines, monitoring systems, and local debugging. No models — reads PHP config/env and queries services directly.

## Consumers
- **Deployment pipelines**: `GET /api/health` post-deploy to confirm readiness (all services 200)
- **Local developers**: `GET /api/health/{service}` when a specific service appears broken
- **CLI / SSH**: `php artisan health:check {?service}` — no auth, formatted table output

## Endpoints
| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /api/health` | auth:sanctum | All services aggregate — 200 healthy, 503 any down |
| `GET /api/health/{service}` | auth:sanctum | Single service drill-down with meta; `{service}` = `app`, `mariadb`, or `redis` |

## Base Response Shape
```json
{
  "service": "redis",
  "status": "ok|degraded|down",
  "code": 200,
  "execution_time_ms": 3,
  "meta": {}
}
```

## How to Add a New Service
1. Create `app/HealthCheck/Checks/YourServiceHealthCheck.php` implementing `HealthCheckInterface`
   - `name(): string` — returns the service key (e.g. `'postgres'`)
   - `check(): HealthStatusData` — uses `hrtime(true)` for timing, catches exceptions → `ServiceStatus::Down`
2. Register the class in `config/health-check.php` under the `checks` array
3. Add service-specific fields to `meta` array inside `check()` as needed
4. Update the DTO's `OA\Property` attributes if the data shape or meta fields change; OA path format is `/api/health` and `/api/health/{service}` (no version prefix in URL — version is header-negotiated)
5. Write unit tests in `tests/Unit/HealthCheck/YourServiceHealthCheckTest.php` — mock the relevant facade

## Architecture Notes
- Uses `lorisleiva/laravel-actions` (`AsAction` trait) — one class handles HTTP (`asController`), CLI (`asCommand`), and future job/listener contexts
- `handle()` is format-agnostic — returns `HealthStatusData` or `HealthStatusData[]`
- Two `#[OA\Get]` attributes on `asController()` — one for `/api/health`, one for `/api/health/{service}`
- `app` is a registered checker (`ApplicationHealthCheck`) like any other service — checks maintenance mode, debug flag, opcache, and memory limit; returns `Ok` or `Degraded` only (never `Down`)

## IoC Bindings

The domain registers its own bindings via `App\HealthCheck\Providers\HealthCheckServiceProvider`, not `AppServiceProvider`. The provider tags all classes listed in `config('health-check.checks')` as `health-checks` and binds `HealthCheckerService` to resolve them.

To add a new checker: implement `HealthCheckInterface`, add the class to `config/health-check.php` under `checks`, and the provider wires it automatically — no changes to the provider or `AppServiceProvider` needed.
