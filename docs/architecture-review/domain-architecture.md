# Review: Domain & Code Architecture

Research for [#40 — "Review: Domain & code architecture"](https://github.com/marcth2/sprig-api-platform/issues/40), part of the [#39 map](https://github.com/marcth2/sprig-api-platform/issues/39).

Scope: the siloed domain layout (`app/{Domain}/{Actions,Data,Contracts,Enums,Providers,Services}`), the Actions/DTO pattern (`lorisleiva/laravel-actions` + `spatie/laravel-data`), and the HealthCheck domain that is the only thing currently built on top of it. Compared against the reference sibling at `/work/projects/marcth2/Laravel/Code/laravel-prototype` (read-only, outside this repo) where divergence is unexplained.

Per the map's tone convention: this is deliberately weighted toward risk, not balanced praise.

---

## Finding 1 — The entire architectural bet is unvalidated, in both this repo and the "more advanced" reference sibling

**Observed today.** `app/` in Sprig contains exactly one domain, `HealthCheck` (`app/HealthCheck/{Actions,Checks,Contracts,Data,Enums,Providers,Services}`, confirmed via `find app -type f`). The reference sibling, `laravel-prototype`, is described by the map (#39 Notes) as "the more-advanced project (8 phases complete)." Reading `docs/agentic/BUILD.md` in that project (`/work/projects/marcth2/Laravel/Code/laravel-prototype/docs/agentic/BUILD.md`, section headers at lines 113, 146, 179, 248, 300, 348, 382, 430) shows all 8 phases were about tooling, hardening, OpenAPI, and Docker for the *same single HealthCheck domain* — never a second domain. `find app -maxdepth 3 -type d` in the prototype shows the identical shape: `Http`, `Models`, `OpenApi`, `Providers`, `Shared`, and one domain, `HealthCheck`.

**Extrapolated.** Every substantive claim CLAUDE.md makes about this architecture — "self-contained," "grow as you go," Contracts as "interfaces the domain exposes or depends on," Services added "only when an Action becomes too complex" — is written as settled convention (CLAUDE.md, "Domain Architecture" and "Design Philosophy" sections) but has never been exercised by a second domain anywhere, including in the project it was copied from. There is no primary-source evidence, in either codebase, that domain boundaries hold up under a real cross-domain dependency, that "grow as you go" avoids costly rework at domain #2, or that the directory convention scales past trivial CRUD-adjacent logic like health checks.

**Severity:** High — this is the foundational bet the whole platform rests on, and the map's own "foundational-first" remediation priority note applies squarely here.
**Triage:** `grilling`. There's a genuine tradeoff: keep deferring cross-domain design until domain #2 forces the issue (consistent with "grow as you go," avoids speculative YAGNI violations), versus proactively deciding the cross-domain coupling mechanism now so domain #2 isn't designed under time pressure with no precedent. Both sides are defensible; it needs a human decision, not a default.

---

## Finding 2 — No documented mechanism for how one domain is meant to depend on another

**Observed today.** CLAUDE.md defines `Contracts/` as "Interfaces the domain exposes or depends on" (CLAUDE.md, Domain Architecture diagram). The only Contract in the codebase, `HealthCheckInterface` (`app/HealthCheck/Contracts/HealthCheckInterface.php:9-14`), is implemented exclusively by classes inside the same domain (`app/HealthCheck/Checks/{ApplicationHealthCheck,MariadbHealthCheck,RedisHealthCheck}.php`) and consumed exclusively by `HealthCheckerService` in that same domain (`app/HealthCheck/Services/HealthCheckerService.php:7,13,19,28`). Nothing in the codebase exposes a Contract *to* another domain or depends *on* another domain's Contract — the phrase "or depends on" in CLAUDE.md's own definition has zero example backing it.

This gap is already known and unresolved outside this repo's tracked history: prior session notes record the user explicitly treating cross-domain coupling (direct dependency vs. events vs. action-calling-action) as "exploratory, undecided" for the next real domain — consistent with what the code shows.

**Extrapolated.** When domain #2 needs domain #1's data or behavior, will it import the class directly (collapses the silo), depend on a container-bound Contract (needs a cross-domain service-location convention that doesn't exist yet), or communicate via an event (loose coupling, but no event-bus convention exists in CLAUDE.md either)? Whoever builds domain #2 has no precedent to follow and will invent the answer under deadline, at which point it becomes precedent by accident rather than by decision.

**Severity:** Medium-high.
**Triage:** `grilling` — a genuine three-way design tradeoff (direct coupling vs. published Contracts vs. events), not a clear-cut fix.

---

## Finding 3 — HealthCheck's own dependency checks trust the implicit default connection instead of the named one they claim to check, and the real fix was never applied

**Observed today.** `MariadbHealthCheck::check()` calls `DB::connection()->getPdo()` (`app/HealthCheck/Checks/MariadbHealthCheck.php:23`) — the *default* connection (`config/database.php:20`, `env('DB_CONNECTION', 'sqlite')`), not an explicit `DB::connection('mariadb')`. `RedisHealthCheck::check()` has the identical pattern: `Redis::connection()` with no name (`app/HealthCheck/Checks/RedisHealthCheck.php:23`).

This exact defect already caused a real failure: [#34](https://github.com/marcth2/sprig-api-platform/issues/34) recorded `GET /api/health/mariadb` returning `503 down` because local `.env` had `DB_CONNECTION=sqlite`, so the "mariadb" check silently ran MySQL-only queries against SQLite. The fix that shipped ([#35](https://github.com/marcth2/sprig-api-platform/pull/35), merged as `08960fc`) only changed `.env.example` to default `DB_CONNECTION=mariadb` (`.env.example:28`) — it did not change the check itself to bind to the named `mariadb` connection. The code today still checks "whatever the default connection happens to be" and merely reports it under the label `mariadb`. Change the default connection again for any reason (a future domain adding a second database, a local override) and the exact same false-negative recurs, silently.

**Severity:** Medium — already burned once in production-adjacent QA, root cause only papered over, not fixed.
**Triage:** `task` — clear-cut correctness fix: bind explicitly, e.g. `DB::connection('mariadb')->getPdo()` and a named Redis connection. No real tradeoff here.

---

## Finding 4 — The Action class is ~85% transport/documentation boilerplate, undercutting its own "readable by Product Owners" design goal

**Observed today.** CLAUDE.md's Design Philosophy states: "`app/{Domain}/Actions/` is a product catalogue — each class name maps directly to a business requirement... written to be readable by Product Owners and AI agents" (CLAUDE.md, "Design Philosophy"). `app/HealthCheck/Actions/CheckServiceHealth.php` is 243 lines. The actual business logic — `handle()` — is 15 lines (lines 28-42). Everything else is transport and documentation plumbing bolted onto the same class by convention: two `#[OA\Get]` attribute blocks spanning 120+ lines (lines 44-165), an `asController()` HTTP adapter (166-179), an `asCommand()` CLI adapter with table-formatting logic (181-216), and a private `flattenMeta()` recursive helper that exists solely to pretty-print CLI output (218-242).

A Product Owner (or an agent) opening this file to understand "what does this business operation do" has to scroll past OpenAPI attribute syntax and CLI table-formatting code to find the 15 lines that matter. This isn't a one-off — it's the direct, structural consequence of two conventions combined: CLAUDE.md's own rule that operation docs "belong on the Action class" (CLAUDE.md, "Conventions for Agentic Work"), and `lorisleiva/laravel-actions`' one-class-many-transports model.

**Extrapolated.** This is already true today at the one domain with the least business logic in the whole platform. A domain with real conditional logic, validation, and authorization will add more lines to `handle()` while the OA/CLI/HTTP boilerplate stays roughly fixed or grows with more response variants — the ratio doesn't obviously get better, and could get worse if an Action gains more than one HTTP verb or more response codes.

**Severity:** Medium.
**Triage:** `grilling` — a real tradeoff between "keep everything about an operation in one file" (current convention, aids discoverability) and "extract OA/presentation concerns to keep Actions lean" (aids the stated Product-Owner-readability goal but adds files/indirection). This overlaps with [#44 — "Review: OpenAPI documentation pipeline"](https://github.com/marcth2/sprig-api-platform/issues/44); flagging here because the root cause is a domain-architecture/Actions-pattern decision (where operation docs live relative to the Action), not an OpenAPI-tooling defect.

---

## Finding 5 — `meta`'s "explicit data contract" is an untyped bag whose real shape lives only in prose

**Observed today.** CLAUDE.md's Design Philosophy claims DTOs "make the data contract explicit without reading implementation code" (CLAUDE.md, "Design Philosophy"). `HealthStatusData::$meta` is typed as a bare `array` (`app/HealthCheck/Data/HealthStatusData.php:31-33`: `/** @var array<string, mixed> */ public readonly array $meta = []`). Its real shape is entirely checker-dependent and is documented only as free text in an `OA\Property` description (`HealthStatusData.php:32`: "For app: includes opcache_enabled, debug_mode... For mariadb: version, max_connections...") and in the domain README's example JSON (`app/HealthCheck/README.md:159-189`) — not in any type the compiler or PHPStan can check. Three checkers already produce three incompatible shapes into the same untyped field (`app/HealthCheck/Checks/ApplicationHealthCheck.php:49-61`, `MariadbHealthCheck.php:40-44`, `RedisHealthCheck.php:36-40`).

**Extrapolated.** This is a `spatie/laravel-data` DTO doing exactly what a plain array would do, with no compile-time or PHPStan-level guarantee about what's inside `meta` — the "explicit contract" promise is honored for every field except the one that varies, which is precisely the field most likely to drift as more checkers/domains are added.

**Severity:** Medium.
**Triage:** `grilling` — a genuine tradeoff. Spatie Data doesn't cleanly express a polymorphic/variant-shaped field; the alternatives (a `Data` subtype per checker with a discriminated union, or accepting the untyped escape hatch as a deliberate pragmatic choice) are both defensible and worth a real decision rather than silent default.

---

## Finding 6 — Silo boundaries are prose convention only; nothing in the validation gate would catch a violation

**Observed today.** CLAUDE.md's five-gate validation pipeline (CLAUDE.md, "Validation Gate": `composer test`, `composer analyse`, `composer format`, `l5-swagger:generate`, `l5-swagger:audit`) enforces test coverage, static-analysis level, formatting, and OpenAPI spec correctness — but nothing enforces the domain-isolation rule itself. "This is the mandatory convention for every domain" (CLAUDE.md, "Domain Architecture") is asserted in prose with no automated check. `composer.json` (both this repo's and the reference sibling's) has no architecture-boundary tool (no `deptrac`, `phpat`, or equivalent) in `require-dev`.

**Extrapolated.** With one domain there is nothing to violate yet, so this is a purely future risk — but it's the kind of gate that's cheap to add before a violation exists and expensive to retrofit after two or three domains have already grown informal cross-imports.

**Severity:** Medium.
**Triage:** `grilling` — contested because it's genuinely arguable whether adding an architecture-boundary tool now is disciplined foresight or exactly the kind of premature process investment "grow as you go" is supposed to prevent.

---

## Finding 7 — CLAUDE.md's stated Service threshold ("only when an Action becomes too complex") isn't actually the rule the one real example follows

**Observed today.** CLAUDE.md: "Add a Service class only when an Action becomes too complex" (CLAUDE.md, "Design Philosophy"). But `CheckServiceHealth::handle()` (`app/HealthCheck/Actions/CheckServiceHealth.php:28-42`) is a thin 15-line pass-through that immediately delegates to `HealthCheckerService::checkAll()`/`checkOne()` (`app/HealthCheck/Services/HealthCheckerService.php:16-34`) — the Action itself was never "too complex." The actual justification for the Service here is that it aggregates multiple `HealthCheckInterface` implementations resolved from the container via tagging (`app/HealthCheck/Providers/HealthCheckServiceProvider.php:16-28`) — a multi-implementation orchestration concern, not Action complexity.

**Severity:** Low-medium — not a bug, but the written rule and the actual precedent disagree, so whoever builds domain #2 has an ambiguous standard to follow: the letter of CLAUDE.md, or the shape of the one example that exists.
**Triage:** `task` — clear-cut wording fix: describe the real criterion used ("a Service aggregates or orchestrates multiple implementations/dependencies," not just "an Action got complex") so the documented rule matches the precedent it's supposedly derived from.

---

## Finding 8 — Stale cross-reference to a directory that was never created in this repo

**Observed today.** `routes/api.php:9` carries the doc comment `@see docs/architecture/README.md`. No `docs/` directory exists anywhere in this repo (confirmed: `find docs` fails). The reference sibling *does* have this file (`/work/projects/marcth2/Laravel/Code/laravel-prototype/docs/architecture/README.md`), which is where this exact comment originated — it appears to have been carried over verbatim during "Reimplement HealthCheck domain" ([#18](https://github.com/marcth2/sprig-api-platform/pull/18), commit `b5d5e97`) without adjusting for the fact that Sprig has no equivalent doc.

**Severity:** Low — cosmetic, but a concrete, checkable defect, and a small symptom of copying conventions from the sibling without verifying they were adapted.
**Triage:** `task` — either write `docs/architecture/README.md` for Sprig (a natural home for the domain-architecture writeup currently only in CLAUDE.md) or delete the dangling reference.

---

## Summary table

| # | Finding | Grounding | Severity | Triage |
|---|---|---|---|---|
| 1 | Architecture unvalidated past domain #1, anywhere | Observed + extrapolated | High | grilling |
| 2 | No cross-domain dependency mechanism defined | Observed + extrapolated | Medium-high | grilling |
| 3 | Health checks trust default DB/Redis connection, not the named one | Observed today | Medium | task |
| 4 | Action class is mostly OA/CLI boilerplate, not business logic | Observed + extrapolated | Medium | grilling |
| 5 | `meta` field is an untyped bag; contract lives in prose | Observed + extrapolated | Medium | grilling |
| 6 | No automated enforcement of domain-silo boundaries | Observed + extrapolated | Medium | grilling |
| 7 | Documented Service threshold doesn't match the one precedent | Observed today | Low-medium | task |
| 8 | Dead `docs/architecture/README.md` reference in `routes/api.php` | Observed today | Low | task |
