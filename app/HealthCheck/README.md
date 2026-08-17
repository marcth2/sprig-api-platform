# HealthCheck Domain

Service liveness and readiness checks for all registered infrastructure dependencies.

> **Interactive docs:** Swagger UI at [http://localhost:8081](http://localhost:8081) — run `php artisan l5-swagger:generate` to refresh after annotation changes.

---

## Contents

- [Endpoints](#endpoints)
- [Authentication](#authentication)
- [Artisan CLI](#artisan-cli)
- [curl Examples](#curl-examples)
- [Example Responses](#example-responses)
- [Adding a New Checker](#adding-a-new-checker)

---

## Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `GET` | `/up` | Public | Liveness — `200` running, `503` during `php artisan down` · Laravel framework built-in |
| `GET` | `/api/health` | Bearer token | Readiness aggregate — all registered services |
| `GET` | `/api/health/{service}` | Bearer token | Single service drill-down |

**Services:** `app` · `mariadb` · `redis`

**Status codes:** `200` healthy · `503` degraded/down or maintenance mode · `401` missing/invalid token · `404` unknown service name

---

## Authentication

These endpoints require a Sanctum Bearer token. In local development, generate one via Artisan tinker.

### Create a test user (first time only)

```bash
docker compose exec app php artisan tinker --execute \
  '\App\Models\User::factory()->create(["email" => "dev@example.com", "password" => bcrypt("password")]);'
```

### Generate a Bearer token

```bash
docker compose exec app php artisan tinker --execute \
  'echo \App\Models\User::first()->createToken("dev")->plainTextToken;'
```

Copy the printed token and export it for use in curl commands:

```bash
export HEALTH_TOKEN="<paste-token-here>"
```

---

## Artisan CLI

The `health:check` command runs the same checks as the HTTP endpoints without requiring authentication.

```bash
# All services — compact summary
docker compose exec app php artisan health:check

# Single service — includes meta details
docker compose exec app php artisan health:check app
docker compose exec app php artisan health:check mariadb
docker compose exec app php artisan health:check redis
```

**Aggregate output:**

```
+---------+--------+------+-----------+
| Service | Status | Code | Time (ms) |
+---------+--------+------+-----------+
| app     | ok     | 200  | 2         |
| mariadb | ok     | 200  | 5         |
| redis   | ok     | 200  | 1         |
+---------+--------+------+-----------+
```

**Single-service output** (meta shown when present):

```
+---------+--------+------+-----------+
| Service | Status | Code | Time (ms) |
+---------+--------+------+-----------+
| app     | ok     | 200  | 0         |
+---------+--------+------+-----------+
+----------------------------+--------+
| Key                        | Value  |
+----------------------------+--------+
| api_version                | 1.0.0  |
| php_version                | 8.4.x  |
| framework_version          | 13.x   |
| environment                | local  |
| maintenance_mode           | false  |
| php_ini.memory_limit       | 128M   |
| php_ini.max_execution_time | 30     |
| php_ini.post_max_size      | 8M     |
| php_ini.opcache_enabled    | true   |
+----------------------------+--------+
```

---

## curl Examples

### Aggregate — all services

```bash
curl -s \
  -H "Authorization: Bearer $HEALTH_TOKEN" \
  -H "Accept: application/json" \
  -H "X-API-Version: 1" \
  http://localhost:8000/api/health | jq
```

### Single service

```bash
curl -s \
  -H "Authorization: Bearer $HEALTH_TOKEN" \
  -H "Accept: application/json" \
  -H "X-API-Version: 1" \
  http://localhost:8000/api/health/app | jq

curl -s \
  -H "Authorization: Bearer $HEALTH_TOKEN" \
  -H "Accept: application/json" \
  -H "X-API-Version: 1" \
  http://localhost:8000/api/health/mariadb | jq

curl -s \
  -H "Authorization: Bearer $HEALTH_TOKEN" \
  -H "Accept: application/json" \
  -H "X-API-Version: 1" \
  http://localhost:8000/api/health/redis | jq
```

---

## Example Responses

### 200 — All services healthy (`GET /api/health`)

```json
{
  "services": [
    {
      "service": "app",
      "status": "ok",
      "code": 200,
      "execution_time_ms": 2,
      "meta": {
        "api_version": "1.0.0",
        "php_version": "8.4.x",
        "framework_version": "13.x",
        "environment": "local",
        "maintenance_mode": false,
        "degraded_reasons": []
      }
    },
    {
      "service": "mariadb",
      "status": "ok",
      "code": 200,
      "execution_time_ms": 5,
      "meta": {
        "version": "11.x-MariaDB",
        "max_connections": "151",
        "threads_connected": "1"
      }
    },
    {
      "service": "redis",
      "status": "ok",
      "code": 200,
      "execution_time_ms": 1,
      "meta": {
        "version": "7.x",
        "used_memory": "1.01M",
        "connected_clients": "1"
      }
    }
  ],
  "healthy": true,
  "checked_at": "2026-06-26T10:00:00.000000Z"
}
```

### 503 — Service degraded or down (`GET /api/health`)

```json
{
  "services": [
    {
      "service": "app",
      "status": "ok",
      "code": 200,
      "execution_time_ms": 1,
      "meta": { "..." : "..." }
    },
    {
      "service": "mariadb",
      "status": "down",
      "code": 503,
      "execution_time_ms": 3,
      "meta": {}
    },
    {
      "service": "redis",
      "status": "ok",
      "code": 200,
      "execution_time_ms": 1,
      "meta": { "..." : "..." }
    }
  ],
  "healthy": false,
  "checked_at": "2026-06-26T10:00:00.000000Z"
}
```

### 401 — Missing or invalid token

```json
{
  "message": "Unauthenticated."
}
```

### 404 — Unknown service (`GET /api/health/unknown`)

```json
{
  "message": "Unknown service"
}
```

---

## Adding a New Checker

1. Create `app/HealthCheck/Checks/YourServiceHealthCheck.php` implementing `HealthCheckInterface`
2. Register it in `config/health-check.php` under `checks`
3. Add an OpenAPI schema to `app/OpenApi/Schemas/HealthCheck/` if a distinct response shape is needed
4. Run `php artisan l5-swagger:generate` to update the spec
5. Add tests in `tests/Unit/HealthCheck/YourServiceHealthCheckTest.php`

See `app/HealthCheck/CLAUDE.md` for the full architectural context and agentic extension guide.
