# Review: Testing & Static-Analysis Gates

Research ticket: [#41 — "Review: Testing & static-analysis gates"](https://github.com/marcth2/sprig-api-platform/issues/41), part of [#39 — "Map: Critical architectural review of Sprig API Platform"](https://github.com/marcth2/sprig-api-platform/issues/39).

Question: critically assess the validation gate (`composer test` at 100%-coverage minimum, PHPStan at `level: max` with no baseline, Pint formatting) — cost/benefit, false-confidence risk, and whether it scales past HealthCheck's near-zero business logic.

Sources read directly: `phpunit.xml`, `phpstan.neon`, `phpstan-bootstrap.php`, `composer.json`, `.github/workflows/ci.yml`, `Dockerfile`, `.githooks/pre-commit`, every file under `app/HealthCheck/` and `app/Console/Commands/AuditOpenApiSpec.php`, every test under `tests/**`, and [#34 — "QA: v0.1.1 HealthCheck domain manual verification"](https://github.com/marcth2/sprig-api-platform/issues/34). Compared against `/work/projects/marcth2/Laravel/Code/laravel-prototype` (read-only reference, outside this worktree) where it diverges.

---

## Finding 1 — The one real production defect found to date was invisible to the entire automated gate

**Grounding:** observed today.
**Severity:** High.
**Triage:** grilling.

[#34](https://github.com/marcth2/sprig-api-platform/issues/34) documents a real defect: a fresh clone's `.env.example` shipped `DB_CONNECTION=sqlite` while `docker-compose.yml` provisions a real `mariadb` service, so `MariadbHealthCheck::check()` ran MySQL-only syntax (`SELECT VERSION()`, `SHOW VARIABLES LIKE ...`) against SQLite, threw, and silently reported `down` in production-shaped conditions. This defect shipped in `v0.1.1` — tagged *before* the QA issue that found it even existed (also flagged in #34's "Process note").

Every commit that shipped this bug passed all three gates in this ticket's scope: `composer test --coverage --min=100`, `composer analyse` (PHPStan max), and `composer format -- --test` (Pint). None of the three is capable, even in principle, of catching this class of bug, because:

- `tests/Unit/HealthCheck/MariadbHealthCheckTest.php:22-32` fully mocks `DB::` (`DB::shouldReceive('connection')`, `DB::shouldReceive('scalar')`, `DB::shouldReceive('selectOne')`) for the "Ok" path, and mocks a fabricated `\Exception` for the "Down" path (line 45). The real body of `MariadbHealthCheck::check()` (`app/HealthCheck/Checks/MariadbHealthCheck.php:19-50` — `DB::connection()->getPdo()`, `DB::scalar()`, `DB::selectOne()`) is never executed against an actual database anywhere in the automated suite.
- `tests/Feature/HealthCheck/CheckServiceHealthTest.php`'s single-service mariadb test (`test_single_service_check_mariadb`, ~line 76) mocks `HealthCheckerService` itself and never reaches `MariadbHealthCheck` at all.
- PHPStan and Pint operate on syntax/types/style, not runtime connection configuration — neither has any way to know `.env.example` disagrees with `docker-compose.yml`.

By contrast, `RedisHealthCheck` gets exactly one real integration touch: `CheckServiceHealthTest::test_single_service_check_redis` (no `HealthCheckerService` mock) calls the real `RedisHealthCheck::check()`, which in CI hits the actual `redis` service container `.github/workflows/ci.yml:29-37`. `.github/workflows/ci.yml:14-27` provisions a real `mariadb` service container specifically so integration-shaped checks are possible, but no automated test — unit or feature — ever calls the real `MariadbHealthCheck` against it. The asymmetry is not deliberate; it looks like an oversight, and it's a one-line-of-test-code fix (mirror the existing real-Redis feature test for mariadb).

**Why this is `grilling` and not just a `task` to add the missing test:** the missing mariadb integration test is a clear, uncontested fix (see the follow-on task note below). The `grilling`-worthy question is the design premise behind it, stated explicitly in `CLAUDE.md`'s "Testing philosophy" section: automated tests own "functional/business-logic correctness" and the wayfinder's manual QA issue owns "real-environment integration seams automated tests structurally can't reach." That split is coherent as written, but #34 shows the boundary is exactly where the real bug lived, and the 100%-coverage number gives no signal that it's a boundary at all — a reader of "100% coverage, PHPStan max, all green" has no way to know, from the gate's output alone, that the mariadb success path has never run against a real database. Worth deciding: should the automated gate require at least one real-infrastructure touch per external dependency (cheap here, since CI already stands the containers up), or is that duplicating what the QA issue is for? Either answer is defensible; it hasn't been decided, only implicitly assumed.

**Follow-on task (uncontested part):** add a feature test exercising the real `MariadbHealthCheck` against the CI mariadb container, mirroring `CheckServiceHealthTest::test_single_service_check_redis`.

---

## Finding 2 — "100% coverage" is line coverage via PCOV; branch/condition coverage is not measured, and real gaps already exist under the 100% number

**Grounding:** observed today (concrete, reproducible example below).
**Severity:** Medium-High (directly undercuts the "100%" framing as a correctness guarantee).
**Triage:** grilling.

`composer.json:52-55` runs `@php artisan test --coverage --min=100`. The `ci` build target that executes this in CI (`.github/workflows/ci.yml:44-50`, `84`) installs and enables **PCOV** (`Dockerfile:76-77`, commented "CI IMAGE — PCOV for coverage"). PCOV is a line-coverage driver — it does not instrument branches or conditions. (The separate dev image installs Xdebug instead — `Dockerfile:49-50, 59` — but that's not what the gate enforces; Xdebug can do branch/path coverage, PCOV structurally cannot.) So the mandate's "100%" is provably 100% of *lines executed at least once*, not 100% of *logical branches exercised* — a materially weaker guarantee than "100% coverage" reads as to anyone who doesn't check which driver is wired in.

This isn't just a theoretical distinction — it's already true in this codebase. `ApplicationHealthCheck::check()` (`app/HealthCheck/Checks/ApplicationHealthCheck.php:31, 36`) guards two of its four degradation checks with `&& ! app()->isLocal()`:

```php
if ((bool) config('app.debug') && ! app()->isLocal()) {          // line 31
    $degraded[] = 'debug_enabled';
}
...
if (($opcacheEnabled === false || $opcacheEnabled === '' || $opcacheEnabled === '0') && ! app()->isLocal()) {  // line 36
    $degraded[] = 'opcache_disabled';
}
```

Grepping `tests/` for `isLocal` or any environment override that would make `app()->isLocal()` true turns up nothing (`grep -rn "isLocal" tests/` — zero matches; `APP_ENV` is `testing` for the whole suite per `phpunit.xml:21`). That means the "local environment suppresses this degradation" half of both branches has **never been executed by any test**, despite `composer test --min=100` passing — because the line itself is hit (short-circuit evaluation still counts as "line covered") every time the non-local half runs. The mandate's 100% number is real, and it is also compatible with two intentional pieces of production logic having zero test evidence behind them.

**Why `grilling`:** whether to accept line coverage as the standard (fast, PCOV, cheap in CI) or pay for branch coverage / add mutation testing (e.g. Infection) for a stronger signal is a genuine cost/benefit tradeoff, not a bug. But the current state means the "100%-coverage mandate" is arguably mis-sold by its own name in `CLAUDE.md` — it should be described as what it actually is (100% line coverage) so nobody downstream treats a green `composer test` as proof every branch was validated.

---

## Finding 3 — PHPStan `level: max` with zero baseline already extracts a real-code cost on a domain with almost no logic; extrapolated to real domains, the discipline needs a stated plan, not just persistence

**Grounding:** the cost is observed today; the "won't scale without a plan" conclusion is extrapolated to future domains.
**Severity:** Medium.
**Triage:** grilling.

`phpstan.neon:1-6` sets `level: max`, `paths: [app]`, no `ignoreErrors`, no `baseline`. In the reference sibling, `laravel-prototype/phpstan.neon` includes a `phpstan-baseline.neon` (checked: 2 lines, essentially empty) — so the prototype also runs effectively clean at max level today, but it at least has the *mechanism* wired for when it isn't. Sprig has no baseline file and no include for one; a first non-trivial PHPStan violation on `master` has no absorption path except fixing it immediately or weakening `phpstan.neon` itself.

The cost is visible already: `MariadbHealthCheck.php:28-36` and `RedisHealthCheck.php:30-32` carry `is_numeric()`/`is_string()` runtime guards and `/** @var ... */` casts whose only job is satisfying PHPStan's `mixed`-return narrowing on `DB::selectOne()` and array-shaped `Redis::info()` — not genuine defensive programming against a realistic runtime failure mode. That's an acceptable, small tax on a two-branch health check. It is not evidence about whether the same discipline holds up once Eloquent models, relations, and Spatie Data casts with real business validation land — those are exactly the areas where Laravel + PHPStan max historically generates the most friction (magic methods, `Model::query()` return types, relation return types, collection generics). Nothing in this repo today proves or disproves that; it's untested by construction, because only one thin domain exists (per the map's own framing).

**Why `grilling`:** keeping `level: max` + zero baseline as a permanent, non-negotiable standard for every future domain is a real decision with real alternatives (accept a baseline for legacy/generated code, drop to a fixed numeric level, or keep max but budget time explicitly for the friction). It hasn't been tested against real business logic yet, so it can't yet be validated — but it also hasn't been explicitly decided as "yes, we're committing to zero baseline forever regardless of what domain #2 needs," which is what continuing to add domains under the current `phpstan.neon` unchanged implicitly commits to.

---

## Finding 4 — Stock Laravel scaffold tests remain in the suite, contributing to file/test counts without asserting anything domain-specific

**Grounding:** observed today.
**Severity:** Low.
**Triage:** task.

`tests/Unit/ExampleTest.php` (`assertTrue(true)`) and `tests/Feature/ExampleTest.php` (asserts `/` redirects) are the unmodified Laravel installer boilerplate. They're harmless and don't inflate the coverage percentage (they don't touch `app/`), but they add noise to "test suite size" as a proxy metric and should be deleted or replaced now that the project has real tests — this is a pure tidiness fix with no tradeoff.

---

## Finding 5 — Pint gate: no notable finding

**Grounding:** observed today.
**Severity:** N/A.
**Triage:** N/A (nothing to triage).

No `pint.json` exists, so Pint runs Laravel's default preset uncustomized across `composer format -- --test` (`composer.json:57`) and CI (`.github/workflows/ci.yml:95-100`). This is the cheapest of the three gates by a wide margin (pure formatting, no semantic risk, sub-second on a codebase this size) and nothing observed here suggests any cost/benefit problem. Included only because the ticket asked for a judgment on all three gates — there's no finding to report.

---

## Summary judgment on the ticket's question

Does 100% coverage "actually catch the bugs that matter for this codebase"? On the one real-world data point available (#34), **no** — the bug that actually shipped and broke a real environment lived entirely in the gap between "line executed under a mock" and "line executed for real," and none of the three gates in scope structurally can see that gap. That's not a knock on the specific test authors — `CLAUDE.md`'s own testing philosophy assigns that exact class of bug to manual QA by design — but the 100% number's framing doesn't communicate that boundary to anyone reading it, and the boundary is unevenly enforced even within HealthCheck (Redis gets a real touch, mariadb doesn't, for no stated reason).

Does the gate scale to domains with real business logic? Unknown and unknowable from this repo alone today (only one thin domain exists), but two structural facts already visible are relevant to that future decision: (1) the coverage mandate measures lines, not branches, so "100%" will keep meaning less than it sounds like as branching logic gets genuinely complex; (2) PHPStan max with zero baseline is currently free only because the domain is trivial, and there's no absorption mechanism (baseline) wired in for when it isn't.
