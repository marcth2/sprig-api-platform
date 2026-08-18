# Research: l5-swagger pipeline, AuditOpenApiSpec, and annotation-placement conventions

Part of #44, child of the map #39.

Primary sources read in full: `app/Console/Commands/AuditOpenApiSpec.php`, `tests/Feature/Console/AuditOpenApiSpecTest.php`, all six fixtures in `tests/Fixtures/OpenApi/`, `app/HealthCheck/Actions/CheckServiceHealth.php`, `app/HealthCheck/Data/HealthAggregateData.php`, `app/HealthCheck/Data/HealthStatusData.php`, `app/Shared/Data/ApiErrorData.php`, `app/Http/Controllers/ApiController.php`, `app/OpenApi/CLAUDE.md`, `config/l5-swagger.php`, `.github/workflows/ci.yml`, `.gitignore`, `.claude/commands/openapi-audit.md`, `.claude/commands/openapi-draft.md`, `composer.json`. Reference sibling read read-only at `/work/projects/marcth2/Laravel/Code/laravel-prototype` (outside this worktree, not modified).

`storage/api-docs/*` is gitignored in this repo (`.gitignore:23-24`, only `.gitkeep` tracked) and was never generated in this worktree, so the actual produced artifact was verified against the reference sibling's committed `storage/api-docs/openapi.yaml` (234 lines) instead, which is generated from a byte-identical `HealthCheck` domain and an identical `AuditOpenApiSpec.php` (confirmed via `diff` — zero output).

---

## Finding 1 — Schema-vs-DTO drift has zero automated detection

**Observed today.** `AuditOpenApiSpec::findIncomplete()` (`app/Console/Commands/AuditOpenApiSpec.php:205-244`) only checks two things per documented route: a missing `operationId` (line 224) and a missing `401` response on `sanctum`-guarded routes (lines 228-233). Nothing in the command, in CI (`.github/workflows/ci.yml:103-133`), or in PHPStan reflects on a `Data` class's actual public properties and diffs them against its `#[OA\Property]` attributes (`app/HealthCheck/Data/HealthStatusData.php:22-33`, `HealthAggregateData.php:21-29`, `app/Shared/Data/ApiErrorData.php:19-21`). The convention ("schema docs on DTOs," `CLAUDE.md` / `app/OpenApi/CLAUDE.md:23-32`) is enforced entirely by developer discipline: rename a property, add a field, or change a type on `HealthStatusData` without touching its `#[OA\Property]` block, and every one of the five validation gates (`composer test`, `composer analyse`, `composer format`, `l5-swagger:generate`, `l5-swagger:audit --fail-on-warnings`) still passes green. The spec silently goes stale.

This is the single most consequential gap in the pipeline given the project's own design philosophy — DTOs are supposed to make "the data contract explicit without reading implementation code" (`CLAUDE.md`, Design Philosophy) — and given how obsessively gated everything else is (100%-coverage tests, PHPStan max, Pint). Schema accuracy is the one thing here nobody actually checks by machine.

- **Severity:** High. Undetected drift in a documented API contract is exactly the failure mode an OpenAPI pipeline exists to prevent.
- **Triage: grilling.** There's a genuine, contested design question here, not a mechanical bug: build a reflection-based DTO↔schema diff into the existing custom command (more bespoke code to maintain), adopt a third-party spec/contract-validation tool, or accept manual review as sufficient at 1-domain/3-DTO scale and revisit later. All three are defensible; none is obviously correct.

---

## Finding 2 — No `operationId` uniqueness check; will surface as copy-paste bugs once there are more than two operations

**Observed today, with an explicitly extrapolated failure mode.** `findIncomplete()` checks only for a *missing* `operationId` (`AuditOpenApiSpec.php:224-226`); it never checks for a *duplicate* one across the whole spec. Right now this is moot — there are exactly two operations (`getHealthAggregate`, `getServiceHealth`, `CheckServiceHealth.php:46,105`) and they're trivially distinct. `operationId` is required to be globally unique per the OpenAPI spec itself, and it's exactly the kind of field that gets copy-pasted-and-forgotten when a second or third domain's Actions are drafted from an existing one as a style template (`.claude/commands/openapi-draft.md:13` explicitly tells the agent to use `CheckServiceHealth.php` as the copy-paste reference for every future Action). A collision would produce an invalid spec — silently, since the audit command's `--fail-on-warnings` gate would not catch it — and would only surface downstream, in a codegen tool or the Swagger UI.

- **Severity:** Medium today (no live bug), rising to high risk as more domains land (extrapolated).
- **Triage: task.** No real tradeoff — this is a straightforward addition to `findIncomplete()`/a sibling check (collect all `operationId`s across `specPaths`, flag duplicates), symmetric with the existing undocumented/phantom logic that already builds signature sets.

---

## Finding 3 — Routes without the `api` middleware are silently excluded from the audit, with no signal that anything was skipped

**Observed today, mechanism only exercised in principle.** `getAuditableRoutes()` (`AuditOpenApiSpec.php:123-153`) skips any route that doesn't carry the `api` middleware (line 132: `! in_array('api', $middleware)`). Today every HealthCheck route qualifies (`routes/api/health.php:8-11`, nested inside `routes/api.php` which Laravel auto-wraps in the `api` group), so the exclusion never fires in practice. But the filter is silent — a route registered outside `routes/api.php` (e.g. a domain author who forgets to route through the domain's `routes/api/{domain}.php` file, per `routes/api.php:6-14`) is dropped from both the "audited routes" count and the undocumented-route check with zero warning. The audit's summary table (`AuditOpenApiSpec.php:64-73`) reports "Routes audited: N" with no indication that N excludes anything — a misrouted endpoint would pass the gate clean while being completely undocumented and untested by this tool.

- **Severity:** Medium, extrapolated — genuinely dormant at 1 domain / 2 routes, becomes a real blind spot the moment a domain's routes aren't wired the conventional way.
- **Triage: task.** Not a design debate — the fix is mechanical: log/report routes excluded for missing the `api` middleware as an explicit informational line, the same way `reportUndocumented`/`reportPhantom`/`reportIncomplete` already report their categories.

---

## Finding 4 — The custom `AuditOpenApiSpec` command is a ~300-line bespoke reimplementation of route↔spec reconciliation, already showing real fragility at n=2 routes

**Observed today.** `app/Console/Commands/AuditOpenApiSpec.php` is 299 lines, backed by an 80-line feature test (`tests/Feature/Console/AuditOpenApiSpecTest.php`) and six hand-written YAML fixtures (`tests/Fixtures/OpenApi/*.yaml`). All of this exists to answer three questions l5-swagger itself doesn't answer: are there undocumented routes, phantom spec paths, or incomplete annotations. The matching logic is pure string equality on `method:uri` signatures (`findUndocumented`, `AuditOpenApiSpec.php:172-180`; `findPhantom`, lines 189-198) — no normalization for trailing slashes, route-model-binding shorthand, or l5-swagger's own path-rendering quirks. This is exactly the kind of matching that degrades non-linearly as route count grows (optional segments, versioned prefixes, nested resource routes all multiply the ways two representations of "the same path" can fail to string-match), and the command has already needed two defensive null-guards for malformed spec shapes (`null-paths.yaml`, `null-path-item.yaml` fixtures) despite a spec of only two paths.

- **Severity:** Medium today; the maintenance-cost trajectory is the extrapolated part — every new domain's routes are more surface area for signature-matching edge cases, and every edge case found becomes another one-off fixture + null-guard in a command with no upstream maintainer.
- **Triage: grilling.** Real alternatives exist (invest further in the bespoke command; replace the reconciliation logic with an existing OpenAPI-validation/contract-testing library; or explicitly cap what this tool promises to catch and rely on manual review for the rest) and the right call plausibly changes once domain #2 has meaningfully more routes than domain #1. Not a clear-cut fix.

---

## Finding 5 — Annotation-to-logic ratio is already lopsided on the one Action that exists; the "readable by Product Owners" goal is already in tension with it

**Observed today.** `CheckServiceHealth.php` is 244 lines. The two `#[OA\Get]` attribute blocks on `asController()` span lines 44–165 — 122 lines, roughly half the file — versus the actual `handle()`/`asController()`/`asCommand()` business logic, which is comparatively terse (e.g. `handle()` itself is 15 lines, 28-42). The project's own stated Design Philosophy is that "`app/{Domain}/Actions/` is a product catalogue... written to be readable by Product Owners" (`CLAUDE.md`, Design Philosophy). A Product Owner opening `CheckServiceHealth.php` today scrolls past over 100 lines of PHP-attribute syntax (nested `OA\Response`/`OA\JsonContent`/`OA\Examples` objects) before reaching anything resembling business logic.

**Extrapolated.** This is one Action with two GET endpoints and no request body. A domain with typical CRUD (create/read/update/delete, each needing its own `#[OA\Post]`/`#[OA\Put]`/`#[OA\Delete]` block with request-body schemas, 422 validation-error responses, and per-field examples) will multiply this ratio, not hold it steady. Nothing in the current convention (`CLAUDE.md`; `app/Http/CLAUDE.md`; `app/OpenApi/CLAUDE.md`) offers a way to factor out repeated response fragments (e.g. a standard 401/422 envelope) — the only sanctioned extraction point (`app/OpenApi/CLAUDE.md:11-19`) is for cross-domain *schemas*, not for reusable *response* or *parameter* fragments, so today's copy-pasted 401/`ErrorResponse` blocks (`CheckServiceHealth.php:74-78,140-144`) are the templated pattern for every future Action, not an exception.

- **Severity:** Medium, primarily a maintainability/readability concern rather than a correctness one.
- **Triage: grilling.** There's a real design choice on the table — keep annotations physically inline on Actions per the stated convention (simplicity, single-file locality) vs. permit shared response/parameter fragments to cut duplication as endpoint count grows (less duplication, but a new "where do reusable non-schema OA fragments live" convention to define, i.e. `app/OpenApi/`'s scope would need to expand beyond "schemas with no domain owner"). This directly bears on whether the annotation burden stays proportional as domains grow, which is the ticket's core question.

---

## Finding 6 — CI's `generate`/`audit` steps carry a full mariadb+redis service dependency they don't need

**Observed today.** The "Generate OpenAPI spec" and "Audit OpenAPI spec" CI steps (`.github/workflows/ci.yml:103-133`) run with `--network host`, `--add-host mariadb:127.0.0.1`, `--add-host redis:127.0.0.1`, and `APP_ENV=testing` — identical infrastructure wiring to the "Run tests" step. But `php artisan l5-swagger:generate` is pure static PHP-attribute reflection over `app/` (`config/l5-swagger.php:50-52`: `annotations => [base_path('app')]`) and `l5-swagger:audit` only parses the resulting YAML and Laravel's route table (`AuditOpenApiSpec.php:127-153`) — neither step executes `HealthCheckerService` or talks to mariadb/redis. The service containers and network wiring are unused overhead for these two steps, just copy-pasted from the test step.

- **Severity:** Low — a CI efficiency/clarity issue, not a correctness risk.
- **Triage: task.** Clear-cut: drop the unneeded `--network host`/`--add-host`/service dependency from the generate and audit steps. No real debate.

---

## Finding 7 — `app/OpenApi/` convention is followed cleanly today; the two "sibling" phrasings of the same rule are cosmetic, not a real divergence

**Observed today.** `app/OpenApi/` in this repo contains only `CLAUDE.md` (47 lines) — no PHP classes — consistent with "do not create standalone schema-holder classes in `app/OpenApi/`" (`CLAUDE.md`, Conventions for Agentic Work; mirrored at length in `app/OpenApi/CLAUDE.md:21-32`). The reference sibling's `app/OpenApi/` is likewise just a `CLAUDE.md` (`/work/projects/marcth2/Laravel/Code/laravel-prototype/app/OpenApi/CLAUDE.md`, 1755 bytes), with the same rule stated in one line in its root `CLAUDE.md:35` ("Do not create `app/OpenApi/Schemas/` files for domain schemas"). Both projects currently have zero classes in `app/OpenApi/`, and all three schemas in each (`HealthAggregateData`, `HealthStatusData`, `ApiErrorData`/equivalent) carry their `#[OA\Schema]` on the owning DTO, per convention. This ticket's premise (a documented wording divergence between the two repos) is confirmed as a **wording** difference only — Sprig's version is more elaborate (defines a `Common/` sub-convention, lines 11-19, and a "how to add a schema correctly" checklist, lines 42-47, that the prototype's one-liner doesn't have) but there is no behavioral or structural divergence to remediate today.

- **Severity:** None — informational only.
- **Triage: task**, and a trivial one at that: if anything, note that Sprig's elaboration is unexercised (no schema has ever needed the `Common/` escape hatch, `app/OpenApi/CLAUDE.md:38`) — not a defect, just an untested convention.

---

## Summary table

| # | Finding | Basis | Severity | Triage |
|---|---|---|---|---|
| 1 | No automated DTO↔schema drift detection | Observed | High | Grilling |
| 2 | No `operationId` uniqueness check | Observed / extrapolated | Medium→High | Task |
| 3 | Non-`api`-middleware routes silently excluded from audit, no signal | Observed / extrapolated | Medium | Task |
| 4 | Bespoke ~300-line audit command already fragile at 2 routes | Observed / extrapolated | Medium | Grilling |
| 5 | Annotation bulk already dominates the one Action file; no reuse mechanism | Observed / extrapolated | Medium | Grilling |
| 6 | CI generate/audit steps carry unneeded DB/cache service wiring | Observed | Low | Task |
| 7 | `app/OpenApi/` convention followed cleanly; sibling divergence is cosmetic wording only | Observed | None | Task |
