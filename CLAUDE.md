# CLAUDE.md — Sprig API Platform

Agentic project instructions for Claude Code. Read this file before making any changes.

---

## Design Philosophy

**Actions are named business operations. DTOs are their data contracts.**

Both are written to be readable by Product Owners and AI agents. `app/{Domain}/Actions/` is a product catalogue — each class name maps directly to a business requirement. DTOs make the data contract explicit without reading implementation code.

**Grow as you go.** Start with an Action and a DTO. Add a Service class when an Action needs to orchestrate multiple collaborators — e.g. resolving a tagged collection of implementations via IoC, as `HealthCheckerService` does for the HealthCheck domain's checkers — not as a place to move logic just because an Action feels long. Add repositories, events, and query objects only when the need is real — not in anticipation of it.

---

## Project Purpose

Sprig API Platform — a Laravel platform that starts as a polished, reusable template and becomes the home for migrated services over time.

Phase one: reimplementing the HealthCheck domain and engineering conventions of a reference prototype under this branding and a simplified trunk-based git workflow. Future domains arrive alongside future migrated services and are out of scope for now.

---

## Domain Architecture

This project uses a siloed domain architecture. Each domain lives under `app/{Domain}/` and is self-contained, pulling in only the subdirectories it needs:

```
app/{Domain}/
├── Actions/     # One class per use case (lorisleiva/laravel-actions)
├── Data/        # Spatie Data DTOs — request input and response output
├── Contracts/   # Interfaces the domain exposes or depends on
├── Enums/       # Domain enumerations
├── Providers/   # Domain-specific ServiceProvider(s), registered in bootstrap/providers.php
├── Services/    # External service calls, orchestration, data access
└── CLAUDE.md    # Domain documentation (required)
```

Not every domain needs every subdirectory — a simple domain may only have `Actions/` and `Data/`. Add a subdirectory when there's a real class to put in it, not in anticipation of one.

This is the mandatory convention for every domain added to this codebase, including domains introduced later for migrated services.

---

## Validation Gate

Run before marking any implementation task complete. All five gates must pass:

```bash
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app composer format -- --test
docker compose exec app php artisan l5-swagger:generate
docker compose exec app php artisan l5-swagger:audit --fail-on-warnings
```

`composer test` runs the full suite with `--coverage --min=100`. `composer analyse` runs PHPStan at level max with no baseline. `composer format -- --test` runs Pint in dry-run mode; drop `-- --test` to auto-fix. `l5-swagger:generate` regenerates the OpenAPI spec from annotations. `l5-swagger:audit` (a custom command in `app/Console/Commands/AuditOpenApiSpec.php`) fails on undocumented routes, phantom spec paths, or incomplete annotations.

`.github/workflows/ci.yml` runs the same five gates on every push/PR against `master`, using the Dockerfile's `ci` build target (PCOV-enabled).

---

## Conventions for Agentic Work

- **All PHP commands:** run via `docker compose exec app` (no native PHP on host)
- **Pre-commit hook:** `.githooks/pre-commit` runs Pint + PHPStan on staged PHP files. `composer setup` wires it in via `git config core.hooksPath .githooks` — a checkout that skips `composer setup` must run that command manually
- **Trunk-based git:** a single `master` branch, no `develop`. Never commit directly to `master` — create a branch, commit there, push, open a PR targeting `master`, and merge
- **No AI attribution:** never include AI-attribution text (e.g. "Generated with Claude Code") in commit messages, PR descriptions, or GitHub issues
- **Domain CLAUDE.md:** every domain directory under `app/` gets a `CLAUDE.md` documenting: purpose, consumers, how to extend, auth model, and any non-obvious patterns
- **OpenAPI annotations:** global spec annotations (`OA\Info`, `OA\Server`, `OA\Tag`) live on `app/Http/Controllers/ApiController.php`. The `sanctum` security scheme is defined in `config/l5-swagger.php`'s `securityDefinitions` instead — l5-swagger's config-driven definitions override an `#[OA\SecurityScheme]` annotation, so don't add one to `ApiController` (#57 removed a dead duplicate that had silently drifted from the config's text). Operation docs (`OA\Get`, `OA\Post`, etc.) belong on the Action class. Schema docs (`OA\Schema`, `OA\Property`) belong on the DTO class — do not create standalone schema-holder classes in `app/OpenApi/`. `storage/api-docs/*` is a generated artifact — never hand-edit it
- **VERSION file:** `VERSION` (project root) is the single source of truth for `APP_VERSION` and `API_VERSION`. Do not add version numbers to `.env`
- **Wayfinder type:** every `wayfinder:map` issue also carries `wayfinder:build` or `wayfinder:portfolio`, set at charting time. `wayfinder:build` is many tickets converging on one integrated deliverable (e.g. #14's HealthCheck domain); `wayfinder:portfolio` is independent, individually-gated decisions/fixes (e.g. #39) with no single integrated artifact. The close-gate below applies a different completeness rule per type — see [ADR-0001](docs/adr/0001-wayfinder-close-gate-and-portfolio-wayfinders.md)
- **Wayfinder → QA → release sequencing:** a wayfinder does not close when its tickets close — completing tickets triggers verification, and verification gates the close. This is technically enforced by `.github/workflows/wayfinder-close-gate.yml`: on `issues.closed` for a `wayfinder:map` issue, it reopens the issue (with a comment naming what's outstanding) unless every child satisfies its type's rule. For `wayfinder:build`: (1) all tickets closed, (2) a QA issue labeled `quality assurance` exists as a child and is closed — anchor it to the commit SHA that closed the last ticket, with a manual verification checklist and comments showing the actual command run + actual output for each check, good faith effort not foolproof, real evidence over a rubber-stamp, (3) only then cut the release. Any defect-fix PR that QA surfaces gets added as its own line in the wayfinder's own checklist, not just mentioned in a QA-issue comment — #14's checklist originally left PR #35 (the mariadb fix #34 surfaced) invisible outside a comment, which this now corrects. For `wayfinder:portfolio`: every `wayfinder:task` child must have a merged PR linked, every other child just needs to be closed — no aggregate QA issue. A release timestamped before its wayfinder closed is a process defect, not a technicality; `.github/workflows/wayfinder-release-checkpoint.yml` flags — not reverts — that case on tag push, since releases are immutable on this repo: it reads the pushed tag's `CHANGELOG.md` section, finds any `#NN` reference that's a still-open `wayfinder:map` issue, and comments on it. This only works if the `CHANGELOG.md` entry actually cites the wayfinder issue number — always include it, alongside the QA issue number where one exists. The minor-vs-major choice is judged by the wayfinder's actual compatibility impact (additive → minor, breaking → major) — it is not automatic just because a wayfinder finished. Patches are reserved for narrowly-scoped defect fixes, not a wayfinder's own planned deliverable — the distinguishing test is scope, not timing. In practice both patches shipped so far landed while wayfinder #14 was still open, not between wayfinders as this line previously claimed: v0.1.1 shipped in the gap where #14 had been prematurely closed before its QA issue even existed (the exact ordering gap #36 later fixed), and v0.1.2 shipped during #14's QA gate, fixing the defect #34 surfaced. While pre-1.0, treat "minor" as "a wayfinder's worth of change," not a stability promise — reserve an actual `1.0.0` bump for a deliberate, separate decision that the API is a stable public contract. The `CHANGELOG.md` entry references the driving wayfinder's issue number (required, so the release-checkpoint check can find it) and, for a build wayfinder, its QA issue number too
- **Known process gap — PR #36:** the fix for the original #14/#34 sequencing gap was itself self-authored, self-reviewed (zero reviews), and self-merged by the same actor who wrote the rule, violated it, and fixed it. Recorded here rather than left silent; whether implementer/QA-verifier independence should be required going forward is an open question tracked in #73
- **Testing philosophy — automated vs. manual QA scope:** unit and feature tests own functional/business-logic correctness, whatever a given domain's internal architecture turns out to be — that's what `composer test`'s 100%-coverage gate enforces on every push. A wayfinder's manual QA issue is not a second pass at that same correctness; its job is verifying the real-environment integration seams automated tests structurally can't reach (a real external dependency actually responding, a real queue worker actually consuming a job, a migration actually applying cleanly against real data), plus a targeted spot-check of any shared/cross-cutting code the wayfinder touched. A domain with little internal business logic (e.g. HealthCheck) leans almost entirely on this real-environment check since there isn't much else to verify; a domain with real business logic leans on its automated suite for logic correctness and reserves the QA issue for the seams outside that suite's reach
- **Release mechanics:** before tagging, update `VERSION`'s `APP_VERSION` to match the release being cut — it has drifted from the actual tag before (#48) and leaks into the public OpenAPI spec's `info.version`. Then a `CHANGELOG.md` entry (Keep a Changelog format) also gets an annotated tag and a GitHub Release, so a stable point is clonable/checkoutable instead of only `master` HEAD: `git tag -a vX.Y.Z -m '...' && git push origin vX.Y.Z && gh release create vX.Y.Z --notes-file <changelog-section>`. Immutable releases are enabled on this repo, so a published release's tag/assets can't be edited after the fact — get the notes right before publishing
- **Keep this file current:** after completing a phase, adding a convention, or changing how the project is built or run — update `CLAUDE.md` before ending the session

---

## Agentic Tooling

- **Laravel Boost:** `boost.json` configures guidelines, MCP, and the `laravel-best-practices` skill. The MCP server is wired in `.mcp.json` and runs via `docker compose exec -T app php artisan boost:mcp`
- **Boost MCP tool usage:** reach for these over an ad hoc alternative — `search-docs`: always run before coding against a Laravel-ecosystem package (Laravel, Sanctum, Pest, etc.); it returns version-specific documentation for this project's actual installed versions, not general knowledge. `database-query`: run a read-only SQL query (`SELECT`/`SHOW`/`EXPLAIN`/`DESCRIBE`) against a configured connection instead of shelling into `tinker` or `mysql`. `database-schema`: inspect table/column/index/foreign-key structure — call with `summary` first for an overview, then again with `filter` for full details on specific tables. `get-absolute-url`: resolve a relative path or named route to its absolute URL instead of hand-constructing one. `browser-logs`: read recent frontend/JS console output when debugging client-side behavior
- **Skills:** `.claude/skills/laravel-best-practices/` — apply when writing, reviewing, or refactoring Laravel PHP code
- **Commands:** `.claude/commands/` — `/validate` runs the five-gate check; `/ship` validates then walks through commit/push/PR with explicit developer confirmation; `/test` runs targeted or full test runs; `/issue <n>` starts a branch from an existing GitHub issue; `/openapi-audit` and `/openapi-draft` support the OpenAPI workflow
- **Local settings:** `.claude/settings.local.json` is personal and gitignored — do not commit it

---

_Last updated: 2026-08-20 (Restored Boost MCP tool-usage guidance dropped during the port from the reference sibling, #61)_
