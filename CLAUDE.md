# CLAUDE.md — Sprig API Platform

Agentic project instructions for Claude Code. Read this file before making any changes.

See [`docs/architecture.md`](docs/architecture.md) for the design philosophy and domain architecture, and [`docs/way-of-working.md`](docs/way-of-working.md) for git/CI conventions, the wayfinder process, and agentic tooling.

---

## Project Purpose

Sprig API Platform — a Laravel platform that starts as a polished, reusable template and becomes the home for migrated services over time. The first migration target is a legacy CodeIgniter application.

Phase one: reimplementing the HealthCheck domain and engineering conventions of a reference prototype under this branding and a simplified trunk-based git workflow. Future domains arrive alongside future migrated services and are out of scope for now.

---

## Validation Gate

Run before marking any implementation task complete. All six gates must pass:

```bash
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app composer format -- --test
docker compose exec app composer lint
docker compose exec app php artisan l5-swagger:generate
docker compose exec app php artisan l5-swagger:audit --fail-on-warnings
```

`composer test` runs the full suite with `--coverage --min=100`. `composer analyse` runs PHPStan at level max, via `larastan/larastan` for Laravel-aware type inference (Eloquent, facades, container resolution), with no baseline. `composer format -- --test` runs Pint in dry-run mode; drop `-- --test` to auto-fix. `composer lint` runs PHP_CodeSniffer against `phpcs.xml`, which enables only `Generic.Files.LineLength` (120 chars) — scoped narrowly because full PSR-12 would actively fight Pint's `laravel` preset on style rules the two tools don't agree on byte-for-byte (import ordering, blank-line placement). `l5-swagger:generate` regenerates the OpenAPI spec from annotations. `l5-swagger:audit` (a custom command in `app/Console/Commands/AuditOpenApiSpec.php`) fails on undocumented routes, phantom spec paths, or incomplete annotations.

**PHPStan escape hatch:** if level-max friction ever becomes real (a violation that isn't a genuine bug and can't be resolved by narrowing types further), suppress it inline with `@phpstan-ignore-line` plus a mandatory one-line justification comment explaining why — reviewed per-occurrence in the PR diff that introduces it. Never add a bulk `phpstan-baseline.neon`: it freezes an entire snapshot of errors with no per-occurrence review or stated reason. `phpstan.neon` sets `reportUnmatchedIgnoredErrors: true`, so a suppression that no longer matches any error fails CI instead of silently accumulating. Decided in [#69](https://github.com/marcth2/sprig-api-platform/issues/69) — see map #39's "Decisions so far" for the reasoning.

`.github/workflows/ci.yml` runs the same six gates on every push/PR against `master`, using the Dockerfile's `ci` build target (PCOV-enabled).

**Keep this file and `docs/` current:** after completing a phase, adding a convention, or changing how the project is built or run — update the relevant file (this one for project purpose/validation gate, `docs/architecture.md` or `docs/way-of-working.md` otherwise) before ending the session.

---

_Last updated: 2026-08-22 (Split architecture and way-of-working content into `docs/`, #153/#174)_
