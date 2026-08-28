<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Sprig API Platform

A Laravel 13 platform that starts as a polished, reusable team starter template and becomes the home for migrated services over time. The first migration target is a legacy CodeIgniter application. Phase one reimplements the HealthCheck domain and engineering conventions of an internal prototype under this branding and a simplified trunk-based git workflow.

---

## Contents

- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Services](#services)
- [Swagger UI](#swagger-ui)
- [Common Commands](#common-commands)
- [Testing](#testing)
- [Architecture](#architecture)
- [Stack](#stack)
- [Agentic Development](#agentic-development)

---

## Requirements

- Docker Engine 24+ (no native PHP or Composer required)
- Claude Code (for agentic development)

---

## Quick Start

```bash
# Start all services
docker compose up -d

# First-run setup: composer install, .env, key:generate, migrate,
# and the pre-commit hook (Pint + PHPStan)
docker compose exec app composer setup

# Generate OpenAPI spec (storage/api-docs/ is gitignored)
docker compose exec app php artisan l5-swagger:generate
```

---

## Services

| Service | Description | Local URL |
|---------|-------------|-----------|
| **Laravel** | PHP 8.5-FPM application served via Nginx | http://localhost:8000 |
| **Mailpit** | Local SMTP capture — intercepts all outbound mail | http://localhost:8026 |
| **Swagger UI** | OpenAPI documentation viewer | http://localhost:8081 |
| **MariaDB 11** | Primary relational database (MySQL-compatible) | localhost:3307 |
| **Redis 7** | Cache, sessions, and queue backend (phpredis) | localhost:6380 |

> Host ports are offset from defaults to avoid conflicts with other local Docker projects.

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /up` | Public | Liveness probe — `200` running, `503` during maintenance mode |
| `GET /api/health` | Bearer token | Readiness aggregate — all registered services |
| `GET /api/health/{service}` | Bearer token | Single service drill-down (`app`, `mariadb`, `redis`) |

---

## Swagger UI

Interactive API documentation is available at [http://localhost:8081](http://localhost:8081).

The OpenAPI spec (`storage/api-docs/`) is gitignored and must be generated locally:

```bash
docker compose exec app php artisan l5-swagger:generate
```

Endpoints require a Sanctum Bearer token — see the [HealthCheck README](app/HealthCheck/README.md#authentication) for instructions on generating one.

---

## Common Commands

```bash
# Artisan
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>

# Run tests (100% coverage required)
docker compose exec app composer test

# Rebuild PHP image
docker compose build app

# View logs
docker compose logs -f app
docker compose logs -f nginx
```

---

## Testing

The project targets **100% test coverage**. Use `/validate` (Claude command) to run all six gates in sequence before any commit.

```bash
# Full test suite — 100% coverage required, fails below threshold
docker compose exec app composer test

# Run a specific test file
docker compose exec app php artisan test tests/Feature/HealthCheck/CheckServiceHealthTest.php

# Run a specific test by name
docker compose exec app php artisan test --filter=test_check_redis_returns_ok
```

### Validation Gate (all six must pass)

```bash
# 1. Tests — 100% coverage
docker compose exec app composer test

# 2. Static analysis — level max, no suppressed errors
docker compose exec app composer analyse

# 3. Code style — must produce no changes
docker compose exec app composer format -- --test

# 4. Line-length lint
docker compose exec app composer lint

# 5. Regenerate OpenAPI spec
docker compose exec app php artisan l5-swagger:generate

# 6. Audit OpenAPI spec — undocumented routes, phantom paths, incomplete annotations
docker compose exec app php artisan l5-swagger:audit --fail-on-warnings
```

Run `/validate` in Claude Code to execute all six gates automatically.

---

## Architecture

This project uses a **custom siloed domain architecture** — not the default Laravel MVC structure. Each domain under `app/{Domain}/` is self-contained and built around two non-default packages:

- [`lorisleiva/laravel-actions`](https://laravelactions.com) — one class per use case, callable as an HTTP controller, Artisan command, queued job, or event listener via the `AsAction` trait
- [`spatie/laravel-data`](https://spatie.be/docs/laravel-data) — type-safe DTOs used as both domain objects and HTTP response payloads

See [CLAUDE.md](CLAUDE.md) for the full domain architecture convention and API versioning strategy.

Each domain has its own `README.md` with authentication instructions, endpoint reference, CLI commands, and curl examples. `CLAUDE.md` in the same directory provides agentic context for Claude Code.

| Domain | Purpose | README | CLAUDE.md |
|--------|---------|--------|-----------|
| HealthCheck | Service liveness and readiness checks | [app/HealthCheck/README.md](app/HealthCheck/README.md) | [app/HealthCheck/CLAUDE.md](app/HealthCheck/CLAUDE.md) |

---

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13.x |
| PHP | 8.5-fpm-alpine (multi-stage: base / dev / ci) |
| Web server | Nginx Alpine → PHP-FPM |
| Database | MariaDB 11 |
| Cache / Sessions / Queues | Redis 7 (phpredis) |
| Mail | Mailpit |
| API Docs | Swagger UI |

---

## Agentic Development

This project uses [Laravel Boost](https://laravel.com/docs/ai) with an MCP server configured in `.mcp.json`. Claude Code loads it automatically, providing tools for documentation search, database inspection, and browser log access.

See [CLAUDE.md](CLAUDE.md) for full agentic engineering instructions and conventions.

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
