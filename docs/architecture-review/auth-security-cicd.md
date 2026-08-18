# Review: Auth, Security & CI/CD Hygiene

Research for [#45](https://github.com/marcth2/sprig-api-platform/issues/45), part of the
[architectural review map (#39)](https://github.com/marcth2/sprig-api-platform/issues/39).

Scope: the Sanctum bearer-token auth model, branch protection ruleset, Dependabot config,
trunk-based git workflow, and pre-commit hook setup — assessed against primary sources
(live GitHub API/GraphQL state, not docs claims) as of 2026-08-18, commit `98e581b`.

Per the map's Notes: every finding is labeled **observed** (true today, verified against a
primary source) or **extrapolated** (reasoned projection to more domains/contributors), given
a rough severity, and triaged as **task** (clear-cut, just fix it) or **grilling** (contested
tradeoff, needs a human decision).

---

## Finding 1 — Branch protection ruleset is real, but is decorative for every self-authored PR

**Grounding:** observed. **Severity:** high. **Triage:** grilling.

The ruleset exists and is active — confirmed via GraphQL introspection (the REST
`branches/master/protection` and `rulesets` endpoints both 403 with "Upgrade to GitHub Pro or
make this repository public," because branch-protection/rulesets reads over REST are gated on
plan tier for private repos; GraphQL is not):

```
rulesets(first: 10) { nodes { name enforcement } }
→ [{"name":"Protect master","enforcement":"ACTIVE"}]
```

Full rule set (GraphQL, ruleset id `RRS_lACqUmVwb3NpdG9yec5Ptx18zgE_xr0`), matching what
[issue #14's comment on ticket #12](https://github.com/marcth2/sprig-api-platform/issues/14)
recorded when it was applied:

- `DELETION` blocked, `NON_FAST_FORWARD` blocked (no force-push), `REQUIRED_LINEAR_HISTORY`
- `PULL_REQUEST`: `requiredApprovingReviewCount=1`, `requireCodeOwnerReview=true`
- `REQUIRED_STATUS_CHECKS`: context `ci`
- `bypassActors`: **one actor, `bypassMode: ALWAYS`** — the repository-admin role per the #14
  comment ("bypass = repository admin role")

`.github/CODEOWNERS:3` is `* @marcth2` — blanket ownership to the same single account that is
the repo's only admin and its only contributor. GitHub does not allow a PR author to approve
their own PR. Cross-checking every merged PR's actual reviews via GraphQL confirms this in
practice:

| PRs | Author | Reviews | Merged by |
|---|---|---|---|
| #15–25, #30–33, #35–36, #38 (23 PRs) | marcth2 | **0** | marcth2 (self, via bypass) |
| #26–29 (Dependabot version bumps) | dependabot[bot] | **1 each** | marcth2 |

Every self-authored PR (i.e., all real feature/fix work) has zero reviews and was merged by
bypassing the ruleset — not because the maintainer is cutting corners, but because a
solo-maintainer + blanket-CODEOWNERS setup makes `requiredApprovingReviewCount=1` and
`requireCodeOwnerReview=true` mathematically unsatisfiable without a second human. The only PRs
that ever cleared the review rule "for real" are the four bot-authored Dependabot bumps, where
marcth2 isn't the author and can legitimately approve.

Because `bypassMode: ALWAYS` applies to the whole ruleset for that actor, not per-rule, the
required-status-check (`ci`) and linear-history rules are *also* bypassed for every admin
merge — they've simply never been tested by a failing CI run merging anyway, since CI has
passed on all 23 self-authored PRs.

**Why this is a genuine tradeoff, not a quick fix:** there is no config that gives a solo
maintainer "real" required review — either accept that review enforcement is currently a no-op
(and be honest about it, e.g. relying on CI-required-checks as the only real gate — but even
that is bypassed for admin merges, not just the review rule), or restructure now:
- Scope the bypass actor more narrowly if the ruleset API allows keeping `REQUIRED_STATUS_CHECKS` enforced even for admin merges, dropping only the review requirement, so CI failure can never be merged past even by the owner.
- Or accept current behavior as intentional for phase one and explicitly document "branch protection today enforces no-force-push/no-delete/linear-history only; review + status-check requirements activate in practice once a second contributor exists" — rather than let CHANGELOG.md's description read as if review gating is currently operative.
- Decide the CODEOWNERS/reviewer model *before* a second contributor or domain lands, since retrofitting a bypass-heavy ruleset after someone else's PR gets stuck on an unsatisfiable review requirement is a worse time to have this conversation.

This is the most consequential finding for "what happens as domains multiply": today's ruleset
gives false confidence that PR review is a real gate. It isn't yet, structurally can't be while
solo, and the failure mode (someone assumes review already happened) only bites once a second
contributor's PR interacts with the same rules for the first time.

---

## Finding 2 — The documented primary consumer of the auth-gated health endpoint has no way to authenticate outside localhost

**Grounding:** observed. **Severity:** high. **Triage:** grilling.

`routes/api/health.php:8` gates both health routes behind `auth:sanctum`:

```php
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/health', CheckServiceHealth::class);
    Route::get('/health/{service}', CheckServiceHealth::class);
});
```

`app/HealthCheck/CLAUDE.md` (Consumers section) states the primary consumer is: *"Deployment
pipelines: `GET /api/health` post-deploy to confirm readiness (all services 200)."* But there is
no token-issuance endpoint, artisan command, or seeder anywhere in the codebase — confirmed by
searching the whole tree for `createToken`, `NewAccessToken`, and any auth/login controller:
none exist except the test suite's `actingAs($user, 'sanctum')` (a test double that bypasses real
token issuance entirely — see `tests/Feature/HealthCheck/CheckServiceHealthTest.php:36,55,65,81,91,114,140`).

The only documented way to obtain a token at all is `app/HealthCheck/README.md`'s
"Authentication" section, and it is explicitly scoped to local development:

> "In local development, generate one via Artisan tinker."
> ```
> docker compose exec app php artisan tinker --execute '...createToken("dev")->plainTextToken;'
> ```

Nothing in `.github/workflows/ci.yml`, `Dockerfile`, `docker-compose.yml`, or anywhere else
describes or provisions a token for staging/production/CI use (grepped for `HEALTH_TOKEN`,
`createToken`, `SANCTUM_TOKEN` across workflow/compose/Dockerfile — zero hits). So the domain's
own stated primary consumer — a deployment pipeline calling `/api/health` post-deploy — has no
implemented or documented path to a credential. Either that check is currently done by a human
manually copying a tinker-generated token (fragile, undocumented, not reproducible), or it isn't
actually being run post-deploy at all despite being the documented purpose of the endpoint.

**The tradeoff underneath this (the ticket's "is this the right auth model" question):** Sanctum
personal access tokens are a `User`-scoped, human-oriented credential primitive (`HasApiTokens`
on `app/Models/User.php:14`; tokens created via `$user->createToken(...)`). A deployment pipeline
or monitoring probe isn't a "user" — modeling it as one means every infra consumer needs a
`User` row to hang a token off of, with no first-class concept of a service account, no ability
scoping in use (see Finding 4), and no rotation/revocation story. A static shared secret / API
key header, or simply leaving the readiness endpoint unauthenticated and relying on network
placement (the same posture Laravel's own built-in `/up` uses, wired in
`bootstrap/app.php:14` `health: '/up'`, and explicitly `Public` per
`app/HealthCheck/README.md`'s endpoint table), would fit a machine-to-machine readiness check
with materially less infrastructure. This is a real design decision, not a bug — hence grilling,
not task.

---

## Finding 3 — Dependabot has no `npm` ecosystem despite real JS dependencies

**Grounding:** observed. **Severity:** medium. **Triage:** task.

`.github/dependabot.yml:1-13` configures exactly three ecosystems:

```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule: { interval: "weekly" }
  - package-ecosystem: "docker"
    directory: "/"
    schedule: { interval: "weekly" }
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule: { interval: "weekly" }
```

`package.json` (root) has real `devDependencies` — `@tailwindcss/vite`, `concurrently`,
`laravel-vite-plugin`, `tailwindcss`, `vite` — plus an `optionalDependencies` entry
(`@laravel/multiplex`). None of these get Dependabot version-update or vulnerability coverage
because there is no `package-ecosystem: "npm"` block. This is unambiguous — no design tradeoff,
just a missing ecosystem entry. Add:

```yaml
  - package-ecosystem: "npm"
    directory: "/"
    schedule: { interval: "weekly" }
```

---

## Finding 4 — Sanctum tokens are unscoped (extrapolated risk)

**Grounding:** extrapolated (no instance of harm today — HealthCheck is the only domain and has
no sensitive mutation). **Severity:** low today / medium at domain #2+. **Triage:** grilling.

The only documented token-creation call, `app/HealthCheck/README.md`'s tinker recipe
(`User::first()->createToken("dev")`), passes no `abilities` array, so Sanctum defaults every
issued PAT to `['*']` — full access to anything any future domain adds behind `auth:sanctum`,
not just health-check reads. Harmless while HealthCheck is the only domain (there's nothing to
over-scope into yet), but this is the seed pattern that gets copy-pasted forward: if domain #2
introduces a mutation-capable endpoint and issues tokens the same way, every existing
health-check token silently already has access to it. Establishing an ability-naming convention
(e.g. `health:read`, `{domain}:{verb}`) before a second domain exists is cheap now and a real
design decision (naming taxonomy, granularity) — not a one-line fix, hence grilling rather than
task.

---

## Finding 5 — Pre-commit hook is opt-in with no automatic enablement

**Grounding:** observed. **Severity:** low. **Triage:** task.

`.githooks/pre-commit` runs Pint + PHPStan on staged PHP files, but it only takes effect after a
developer manually runs `git config core.hooksPath .githooks` (documented in
`CLAUDE.md` under "Conventions for Agentic Work" and in `README.md`). Nothing in the repo
automates this — `composer.json:40-47`'s `setup` script (`composer install`, `.env` copy,
`key:generate`, `migrate`, `npm install`, `npm run build`) does not include it. Confirmed live:
this worktree, a fresh checkout, has no hooks path configured —
`git config core.hooksPath` returns nothing (exit code 1). CI (`.github/workflows/ci.yml`) is a
real backstop that independently runs Pint and PHPStan regardless, so this isn't a gap in the
quality gate itself — it's wasted value from a hook that silently does nothing on most checkouts,
including agent worktrees, until someone remembers the manual step. Fix: add
`git config core.hooksPath .githooks` to the `setup` composer script.

---

## Finding 6 — Trunk-based single-master vs. the prototype's `develop` branch: justified today, worth re-deciding at scale

**Grounding:** observed (today's setup) + extrapolated (what happens at scale).
**Severity:** low today. **Triage:** grilling (revisit at the trigger point, not now).

The reference sibling `laravel-prototype` uses a `develop` branch and PR-to-`develop` workflow
(`CLAUDE.md:33-34` there: *"Use `/ship` to validate, commit, push, and open a PR targeting
`develop`"*; *"Never commit directly to `develop`"*) — no `master`/`develop` merge-forward step is
visible in what was checked, but the two-branch model is the baseline. It also has no
`.github/dependabot.yml`, no `.github/CODEOWNERS`, and no `.githooks/` directory at all — Sprig's
CI/CD hygiene (Dependabot, CODEOWNERS, pre-commit hook) is a net addition over the prototype, not
a regression.

Sprig instead uses a single `master`, framed explicitly in `CLAUDE.md` ("Project Purpose") as
*"a simplified trunk-based git workflow"* for "Phase one." Given `.github/CODEOWNERS:1-3`
explicitly frames this as *"a solo-maintained repo"*, dropping `develop` is well-justified: a
`develop` integration branch exists to buffer multiple concurrent contributors'/features'
in-flight work from a stable trunk before release — with one contributor merging one PR at a
time, there is nothing for `develop` to buffer, and it would just add a redundant merge-forward
step. The divergence is not "unexplained" — it's explained by CLAUDE.md and matches the
solo-maintainer reality confirmed by CODEOWNERS and the all-self-authored PR history in Finding
1.

**Extrapolated risk:** the map's own framing ("what happens as domains multiply") applies here
too, but the real risk isn't the trunk-based model itself — it's that trunk-based-with-required-
review only works as *intended* once review is real (Finding 1 shows it currently isn't). Single
master + fast-merging is a legitimate, well-regarded model for teams with fast CI and real code
review; Sprig has the fast CI half of that bargain (five-gate `composer test`/`analyse`/`format`/
OpenAPI checks on every push, per `CLAUDE.md`'s Validation Gate section) but not yet the review
half. That's fine solo. The decision point isn't "switch to `develop`" — it's "when a second
contributor or domain lands, does the existing ruleset's bypass model actually produce real
review, or does it need to change first" — which is the same decision as Finding 1, viewed from
the workflow-scale angle rather than the ruleset-config angle.

---

## Finding 7 — Sanctum config carries unused SPA/session boilerplate

**Grounding:** observed. **Severity:** low. **Triage:** task.

`config/sanctum.php:21-26` (`stateful` domains) and `config/sanctum.php:40` (`'guard' => ['web']`)
are the Laravel/Sanctum default scaffolding for first-party SPA cookie-session authentication.
Sprig has no SPA and no session-based consumer — the only auth path in use is bearer-token PATs
via `auth:sanctum` on `routes/api/health.php:8`, which Sanctum resolves by looking up the token
directly and does not consult `guard` or `stateful` at all. This is dead configuration left over
from `php artisan install:api`/Sanctum's default publish, not a security defect (no route relies
on it), but it signals the auth config was never pruned to match what the app actually does.
Low priority; either trim it or leave an inline comment noting it's reserved for a future
first-party SPA consumer, so the next reader doesn't assume it's load-bearing.

---

## Summary Table

| # | Finding | Grounding | Severity | Triage |
|---|---|---|---|---|
| 1 | Branch-protection ruleset review requirement is unsatisfiable/bypassed for every solo-authored PR | observed | high | grilling |
| 2 | No token-issuance path exists for the documented deployment-pipeline consumer of `/api/health` | observed | high | grilling |
| 3 | Dependabot missing `npm` ecosystem despite real JS deps | observed | medium | task |
| 4 | Sanctum tokens issued unscoped (`['*']`) — fine now, risky once domain #2 exists | extrapolated | low→medium | grilling |
| 5 | Pre-commit hook not auto-enabled on checkout (composer `setup` script doesn't wire it) | observed | low | task |
| 6 | Trunk-based single-master vs. prototype's `develop`: justified today, re-decide at scale | observed + extrapolated | low | grilling |
| 7 | `config/sanctum.php` carries unused SPA/session boilerplate | observed | low | task |

---

## Primary sources consulted

- `config/sanctum.php`, `routes/api.php`, `routes/api/health.php`, `bootstrap/app.php`
- `app/HealthCheck/CLAUDE.md`, `app/HealthCheck/README.md`, `README.md`
- `app/Models/User.php`, `tests/Feature/HealthCheck/CheckServiceHealthTest.php`
- `.github/dependabot.yml`, `.github/CODEOWNERS`, `.github/workflows/ci.yml`
- `.githooks/pre-commit`, `composer.json`
- `CHANGELOG.md` (repo-hygiene entry), `CLAUDE.md` (Conventions for Agentic Work)
- Live GitHub state: `gh api repos/marcth2/sprig-api-platform/branches/master` and
  `.../rulesets` (both REST-gated by plan tier), and GraphQL `repository.rulesets`/
  `pullRequests(...).reviews` introspection against the live repo
- `gh issue view 14` (comment recording the ruleset's original application)
- Reference sibling `/work/projects/marcth2/Laravel/Code/laravel-prototype` (read-only): its
  `CLAUDE.md`, absence of `.github/dependabot.yml`, `.github/CODEOWNERS`, and `.githooks/`
