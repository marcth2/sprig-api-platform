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
  "state": "ok|degraded|down",
  "status": 200,
  "execution_time_ms": 3,
  "meta": {}
}
```

`meta` is a generic object on the wire (`HealthStatusResource`'s OpenAPI contract stays untyped there), but each checker builds it from a typed `HealthCheckMetaData` implementation — see `AppHealthMeta`, `MariadbHealthMeta`, and `RedisHealthMeta` in `app/HealthCheck/Data/`. The down/failure path and any service with nothing to report use `EmptyHealthMeta`, which serializes to `[]`.

## How to Add a New Service
1. Create `app/HealthCheck/Checks/YourServiceHealthCheck.php` implementing `HealthCheckInterface`
   - `name(): string` — returns the service key (e.g. `'postgres'`)
   - `check(): HealthStatusData` — uses `hrtime(true)` for timing, catches exceptions → `ServiceStatus::Down`
2. Register the class in `config/health-check.php` under the `checks` array
3. Create a `YourServiceHealthMeta` class in `app/HealthCheck/Data/` implementing `HealthCheckMetaData` for any service-specific fields, and construct it inside `check()`. Use `EmptyHealthMeta` (the `HealthStatusData::$meta` default) if the service has nothing extra to report
4. Add an `OA\Schema` to the new meta class documenting its shape; OA path format is `/api/health` and `/api/health/{service}` (no version prefix in URL — version is header-negotiated)
5. Write unit tests in `tests/Unit/HealthCheck/YourServiceHealthCheckTest.php` — mock the relevant facade
6. Every `Checks/` implementation must also have at least one test that exercises the real external system (not a mock) — either test layer (Unit or Feature) satisfies this

## Architecture Notes
- Uses `lorisleiva/laravel-actions` (`AsAction` trait) — one class handles HTTP (`asController`), CLI (`asCommand`), and future job/listener contexts
- `handle()` is format-agnostic — returns `HealthStatusData` or `HealthStatusData[]`
- Two `#[OA\Get]` attributes on `asController()` — one for `/api/health`, one for `/api/health/{service}`
- `app` is a registered checker (`ApplicationHealthCheck`) like any other service — checks maintenance mode, debug flag, opcache, and memory limit; returns `Ok` or `Degraded` only (never `Down`)

## IoC Bindings

The domain registers its own bindings via `App\HealthCheck\Providers\HealthCheckServiceProvider`, not `AppServiceProvider`. The provider tags all classes listed in `config('health-check.checks')` as `health-checks` and binds `HealthCheckerService` to resolve them.

To add a new checker: implement `HealthCheckInterface`, add the class to `config/health-check.php` under `checks`, and the provider wires it automatically — no changes to the provider or `AppServiceProvider` needed.
