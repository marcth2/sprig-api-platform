# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed

- l5-swagger's built-in Swagger UI, leaving the `swaggerapi/swagger-ui` container on `:8081` as the
  only documentation UI. Drops the `api/documentation` and `api/oauth2-callback` routes and the
  published `resources/views/vendor/l5-swagger/index.blade.php` (byte-identical to the package
  default). The `docs` route the container fetches is unchanged.

## [0.2.0] - 2026-08-22

A critical architectural review of the platform's code and process (wayfinder #39) — domain
architecture, testing/static-analysis gates, versioning, the wayfinder→QA→release process itself,
OpenAPI documentation, auth/CI hygiene, and agentic tooling fidelity. 23 merged PRs; three ADRs
record the decisions that were hard to reverse or genuinely contested.

### Added

- Self-healing close-gate (`.github/workflows/wayfinder-close-gate.yml`): reopens a `wayfinder:map`
  issue on close if any child ticket fails its wayfinder type's completeness rule. See
  [ADR-0001](docs/adr/0001-wayfinder-close-gate-and-portfolio-wayfinders.md).
- Non-destructive release-checkpoint check
  (`.github/workflows/wayfinder-release-checkpoint.yml`): flags a tag whose `CHANGELOG.md` entry
  cites a still-open wayfinder.
- A real (non-mocked) mariadb integration test for `MariadbHealthCheck`, closing the coverage gap
  that let the v0.1.1 defect (#34) through undetected.
- DTO-to-OpenAPI schema drift detection (`findDtoSchemaDrift()`), `operationId` uniqueness, and
  missing-`api`-middleware warnings in the `l5-swagger:audit` command.
- npm ecosystem coverage in Dependabot config; a 120-character line-length gate via
  PHP_CodeSniffer.
- `docs/adr/`, with three ADRs: wayfinder types & the close-gate (0001), cross-domain dependency &
  shared Models (0002), QA-verifier independence via fresh-context agent (0003).

### Changed

- `MariadbHealthCheck`/`RedisHealthCheck` now bind to their named connections (`mariadb`/`default`)
  instead of the implicit default — the same class of bug that caused the v0.1.1 defect.
- Wayfinder → verification → release sequencing is now technically enforced (see Added above), and
  implementer/QA-verifier independence is now a fresh-context-agent convention rather than a
  same-session self-check.
- "QA"/"quality assurance" terminology renamed to "manual verification" throughout.
- `API_VERSION` now derives from `APP_VERSION` via `VERSION`-file interpolation instead of a
  parallel hand-maintained array.
- The pre-commit hook is now wired automatically by `composer setup`.

### Fixed

- The close-gate's task-ticket completeness check now recognizes any merged PR that
  cross-references an issue, not only auto-close-keyword links — and its `permissions:` block now
  grants the `pull-requests: read` scope that check actually needs, after the fix initially landed
  without it and produced two false-positive reopens of this very release's wayfinder.
- CI's OpenAPI generate/audit steps no longer carry dead mariadb/redis network wiring.
- The dead duplicate Sanctum `SecurityScheme` annotation on `ApiController`, silently overridden by
  config, removed.

### Removed

- Stock Laravel scaffold tests (`ExampleTest`).
- Unused SPA/session boilerplate from `config/sanctum.php`.

Full decision log: #39.

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
