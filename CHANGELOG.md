# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.2] - 2026-08-18

Patch release fixing a defect found during manual QA of the HealthCheck domain (issue #34).

### Fixed

- `GET /api/health/mariadb` returned `503 down` from a fresh clone even with a healthy `mariadb`
  container. `.env.example`'s `DB_CONNECTION` defaulted to `sqlite`, so Laravel's default database
  connection never touched the provisioned `mariadb` service. `DB_CONNECTION`/`DB_HOST`/`DB_PORT`/
  `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` in `.env.example` now point at `mariadb`, matching the
  credentials `docker-compose.yml` already provisions for that service. No code change — the health
  check itself was correct.

### Changed

- The wayfinder → release convention in `CLAUDE.md` now requires a wayfinder's manual QA issue to
  close clean *before* the wayfinder itself closes, not just before the release — closing the gap
  that let v0.1.1 ship before its own QA issue (#34) existed.

## [0.1.1] - 2026-08-17

Patch release fixing two issues found in review of v0.1.0.

### Fixed

- `VERSION` is now truly the sole source of truth for `APP_VERSION`/`API_VERSION`. A stray
  `API_VERSION=1.0.0` line in `.env.example` meant `cp .env.example .env` permanently pinned
  `API_VERSION`, since Dotenv's immutable loader never overrides an already-set var — silently
  overriding the value `VERSION` was supposed to control. The line is removed, `config/app.php`
  now loads `VERSION` via `Dotenv::create(Env::getRepository(), ...)` (reusing the same
  repository/adapter chain as Laravel's own `.env` loader), and a `'version'` key backed by
  `APP_VERSION` was added to `config/app.php`'s returned array.
- `.github/workflows/ci.yml` now sources its ephemeral CI database credentials from the
  `CI_DB_PASSWORD`/`CI_DB_ROOT_PASSWORD` repo secrets instead of hardcoded `password`/`root`
  values, restoring the reference prototype's convention.

## [0.1.0] - 2026-08-17

First tagged release. Phase one: a from-scratch rebuild of the HealthCheck domain and engineering
conventions of an internal prototype, under Sprig API Platform branding and a simplified
trunk-based git workflow (single `master` branch, no `develop`).

### Added

- Docker Compose dev environment (Nginx, PHP-FPM, MariaDB, Redis, Mailpit, Swagger UI) with a
  multi-stage `Dockerfile` (`base` / `dev` / `ci` / `release` targets).
- Laravel 13 application skeleton under the `laravel-platform` composer identity.
- Header-based API versioning (`X-API-Version`, vendor media type, or configured fallback) via the
  `ApiVersion` middleware and the `VERSION` file as the single source of truth for
  `APP_VERSION`/`API_VERSION`.
- A siloed domain architecture (`app/{Domain}/{Actions,Data,Contracts,Enums,Providers,Services}`)
  built on `lorisleiva/laravel-actions` and `spatie/laravel-data`, documented in `CLAUDE.md`.
- The HealthCheck domain: `GET /up` liveness probe, `GET /api/health` readiness aggregate, and
  `GET /api/health/{service}` single-service drill-down (`app`, `mariadb`, `redis`), behind Sanctum
  bearer-token auth.
- A five-gate validation pipeline — 100% test coverage, PHPStan at level max, Pint formatting,
  OpenAPI spec generation, and a custom `l5-swagger:audit` command catching undocumented routes,
  phantom spec paths, and incomplete annotations.
- OpenAPI documentation generated from annotations (`darkaonline/l5-swagger`) and served via
  Swagger UI.
- Agentic tooling: Laravel Boost with an MCP server (`.mcp.json`), the `laravel-best-practices`
  skill, and Claude Code commands (`/validate`, `/ship`, `/test`, `/issue`, `/openapi-audit`,
  `/openapi-draft`).
- Full "Sprig API Platform" display branding across composer metadata, `README.md`, the OpenAPI
  spec, and `.env.example` (technical slug `laravel-platform` unchanged).
- A trunk-based CI workflow (`.github/workflows/ci.yml`) running the same five validation gates on
  every push/PR against `master`.
- Repository hygiene: `CODEOWNERS`, Dependabot version updates (composer, Docker, GitHub Actions)
  and vulnerability alerts, a `.githooks/pre-commit` hook (Pint + PHPStan on staged PHP files), and
  a branch protection ruleset on `master` (required PR review, required status checks, squash-only
  merges, linear history).
