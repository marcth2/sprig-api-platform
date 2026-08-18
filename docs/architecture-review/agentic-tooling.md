# Review: agentic tooling & convention fidelity

Research ticket [#46](https://github.com/marcth2/sprig-api-platform/issues/46), part of map
[#39](https://github.com/marcth2/sprig-api-platform/issues/39).

Question: is the Laravel Boost/MCP setup, the six Claude Code slash commands (`/validate`,
`/ship`, `/test`, `/issue`, `/openapi-audit`, `/openapi-draft`), and the per-domain `CLAUDE.md`
mandate actually followed and sustainable — or copied from the reference sibling
(`/work/projects/marcth2/Laravel/Code/laravel-prototype`, read-only, outside this worktree)
without the context that motivated them there? Every claim below cites a file path/line, commit
SHA, or PR/issue number — no secondhand summarizing. Worktree HEAD is `98e581b`.

## Headline verdict: the "blind copy" premise is mostly wrong

The ticket's framing suggests the tooling might be cargo-culted. Diffing every file against the
sibling shows the opposite for most of it: the six commands and the MCP wiring were **deliberately
adapted**, not copy-pasted, and PR [#22 — "Add agentic tooling (Laravel Boost, MCP, Claude Code
skills/commands)"](https://github.com/marcth2/sprig-api-platform/pull/22) says so explicitly in
its own body ("adapted for this project's composer scripts and trunk-based (`master`-only)
workflow"). Concretely:

- `.claude/commands/validate.md:4-8` runs `composer test` / `composer analyse` /
  `composer format -- --test` (composer-script wrappers); the sibling's
  `laravel-prototype/.claude/commands/validate.md:4-6` calls the raw binaries
  (`php artisan test --coverage --min=100`, `./vendor/bin/phpstan analyse --memory-limit=-1`,
  `./vendor/bin/pint --test`) directly. Genuine adaptation, not a copy.
- `.claude/commands/ship.md:80-82` targets `master` with `git push -u origin <current-branch>`;
  the sibling's `ship.md:77,80-82` targets `develop` with a bare `git push origin <branch>`.
  Adapted for Sprig's trunk-based workflow.
- `.claude/commands/ship.md:69` drops the sibling's `.omc/` state-file exclusion
  (`laravel-prototype/.claude/commands/ship.md:69`, "Never stage: ... `.omc/` state files") because
  Sprig has no `.omc/` directory. Stale-reference removal, not a leftover.
- `.claude/commands/issue.md` is a full rewrite, not an edit. The sibling's version
  (`laravel-prototype/.claude/commands/issue.md`) creates a GitHub issue **from** a local
  `.omc/plans/` file. Sprig's version (`issue.md:1-16`) does the reverse — it starts a branch
  **from** an already-existing GitHub issue, and says so in its own text: "This project has no
  `.omc/plans/` convention — issues are the persistent record of scope" (`issue.md:16`).
- `.claude/commands/openapi-draft.md:16` says "Use `ref:` to reference schemas defined by
  `#[OA\Schema]` on the owning DTO class ... do not create standalone schema-holder classes in
  `app/OpenApi/`" — rewritten to match Sprig's DTO-owns-schema convention. The sibling's
  `openapi-draft.md:16` instead says "reference existing schemas from `app/OpenApi/Schemas/`",
  the older pattern Sprig explicitly rejected (see `app/OpenApi/CLAUDE.md:21-32`, "Wrong — a
  separate class in `app/OpenApi/Schemas/`").
- `.mcp.json:4-8` wraps the MCP server as `docker compose exec -T app php artisan boost:mcp`; the
  sibling's `.mcp.json:3-9` runs a bare `php artisan boost:mcp` (no Docker wrapper, no `-T`).
  PR #22's body names this adaptation directly: "`.mcp.json` — laravel-boost MCP server, wired via
  `docker compose exec app`".
- `.claude/commands/test.md` and `.claude/commands/openapi-audit.md` are byte-identical between
  the two repos — correctly so, since neither references anything project-specific (both drive
  generic `php artisan test` / `l5-swagger:audit` invocations that don't differ by workflow).
- `laravel/boost` is a real Composer dependency (`composer.json:19`, `"laravel/boost": "^2.4"`),
  confirmed installed in `composer.lock:7499-7560`. The MCP wiring isn't decorative config with
  nothing behind it.

**Grounding: observed today** (direct file diff + PR #22 body). No triage needed — this is a
verdict, not a defect.

## Finding 1 — The mandated branch-naming convention has already been abandoned for untracked work

`.claude/commands/issue.md:7` mandates `<issue-number>-<slug>` branch names (e.g.
`9-agentic-tooling`) for any work started via `/issue`. The first 12 PRs after tooling landed
comply: `9-agentic-tooling` (PR 22), `10-full-rebrand-pass` (PR 23), `11-ci-simplification`
(PR 24), `12-branch-protection-hygiene` (PR 25), `13-v0-1-0-release` (PR 30) all match the pattern
and all have a corresponding numbered GitHub issue (`gh issue list` confirms issues #1–#13 exist,
titled `Task: ...`, one per branch).

Every PR after v0.1.0 breaks the pattern, and none of them has a backing GitHub issue at all
(`gh issue list --state all` shows no issue numbers between #14 and #34 other than the ones map
#39 itself created):

| PR | Branch | Issue backing it |
|---|---|---|
| [#31](https://github.com/marcth2/sprig-api-platform/pull/31) | `fix-version-env-loading` | none |
| [#32](https://github.com/marcth2/sprig-api-platform/pull/32) | `ci-db-secrets` | none |
| [#33](https://github.com/marcth2/sprig-api-platform/pull/33) | `changelog-v0.1.1` | none |
| [#35](https://github.com/marcth2/sprig-api-platform/pull/35) | `fix/mariadb-healthcheck-db-connection` | none (found via QA issue #34, not a ticket) |
| [#36](https://github.com/marcth2/sprig-api-platform/pull/36) | `docs/wayfinder-qa-gates-close` | none |
| [#37](https://github.com/marcth2/sprig-api-platform/pull/37) | `chore/gitignore-handoff` | none (closed unmerged) |

Three of those six (`fix/...`, `docs/...`, `chore/...`) use the sibling prototype's old
type-prefixed naming scheme — the exact convention Sprig's `/issue.md` was written to replace.
This isn't a documented violation, because `CLAUDE.md`'s git conventions bullet
(`CLAUDE.md:68`, "Trunk-based git") only mandates branch-then-PR mechanics, not naming, for work
that doesn't go through `/issue`. The gap is real either way: contributors (human or agent) doing
ad hoc fixes reach for the old sibling muscle-memory naming because Sprig never wrote down what to
do when there's no ticket to number a branch after.

- **Grounding**: observed today (branch names + `gh pr list --json headRefName,baseRefName` +
  absence of matching issues).
- **Severity**: Medium. Cosmetic today (one maintainer, low volume), but it means the
  ticket-driven traceability `/issue.md` was built for already covers a shrinking fraction of real
  merges — 5 of the last 8 non-dependabot PRs bypassed it entirely.
- **Triage: task.** No real debate here — either extend `/issue.md`/`CLAUDE.md` with a naming rule
  for non-ticketed branches (e.g. `fix/<slug>` is fine, just document it), or require even small
  fixes to get a throwaway issue number first. Either resolution is a small doc edit, not a
  tradeoff needing a human decision.

## Finding 2 — The pre-commit hook has never actually been enabled in this repo

`CLAUDE.md:67` documents: "Pre-commit hook: `.githooks/pre-commit` runs Pint + PHPStan on staged
PHP files. Enable it once per checkout with `git config core.hooksPath .githooks`." This is a
manual, per-checkout opt-in with no automated wiring — no Composer `post-install-cmd` script, no
`package.json` `prepare` hook, nothing in `Dockerfile` or `docker-compose.yml` that runs it.

Checked directly: `git config core.hooksPath` in this repository returns nothing (empty, exit
code 1) — worktrees share the repository's `.git/config`, so this reflects the actual repo state,
not just this ephemeral worktree. The hook file itself
(`.githooks/pre-commit:1-27`) is well-built — it no-ops safely if the `app` container isn't
running (`.githooks/pre-commit:12-15`) and runs Pint + PHPStan only on staged PHP files — but it
has never been switched on. Every one of the 37+ commits in this repo's history was validated by
CI (`.github/workflows/ci.yml`) alone, never by the local hook the docs describe as part of the
workflow.

- **Grounding**: observed today (`git config core.hooksPath` empty; no wiring script anywhere in
  the repo).
- **Severity**: Medium-High. The gap doesn't cause defects today — CI (`.github/workflows/ci.yml:
  "Run PHPStan"`, `"Run Pint"`) catches the same violations before merge — but it means one full
  documented gate in `CLAUDE.md`'s "Conventions for Agentic Work" section has a 0% real-world
  activation rate, and nothing surfaces that silently.
- **Triage: task.** Not a tradeoff — wire it into something that runs automatically on
  `composer install` (a `post-install-cmd` in `composer.json` that runs
  `git config core.hooksPath .githooks`) instead of relying on a human/agent remembering a
  one-time manual step documented only in prose.

## Finding 3 — Zero enforcement of the "every domain gets a CLAUDE.md" mandate

`CLAUDE.md:70` states the mandate: "every domain directory under `app/` gets a `CLAUDE.md`
documenting: purpose, consumers, how to extend, auth model, and any non-obvious patterns." It's
honored today — `app/HealthCheck/CLAUDE.md` exists and covers all five required elements
(Purpose: line 3; Consumers: lines 6-9; Endpoints/auth model: lines 12-15, correctly matching the
live routes in `routes/api/health.php:8-9`, both `auth:sanctum`; How to Add a New Service: lines
28-35; Architecture Notes/non-obvious patterns: lines 37-47). `app/Http/CLAUDE.md` and
`app/OpenApi/CLAUDE.md` also exist, documenting shared infrastructure beyond what the mandate
strictly requires (those aren't "domains" under the project's own definition in `CLAUDE.md:27-38`).

But nothing checks this mechanically. `.github/workflows/ci.yml` has no step that verifies a
`CLAUDE.md` exists per `app/{Domain}/` directory, let alone that its content is current — this is
pure-prose convention, identical in enforcement strength to the "Domain CLAUDE.md" bullet's
neighbors (branch naming, no-AI-attribution) that already show drift (Finding 1). With one domain
and ~1 day of history, there's no evidence of staleness yet — but there's also no mechanism that
would catch it if the next domain's author simply forgets, or writes a thin stub.

- **Grounding**: today's single instance is accurate and complete (observed). The
  no-enforcement risk is **extrapolated to future domains** — the map's own two-lens framing
  applies directly: this can't be tested until domain #2 exists.
- **Severity**: Medium. Low blast radius per-incident (a missing/stale doc, not a runtime defect),
  but compounds linearly with domain count and has no backstop.
- **Triage: grilling.** A CI check can trivially verify *existence* (a directory under `app/`
  lacking `CLAUDE.md`), but verifying the five required *content* elements without a rigid schema
  is a real design question — over-engineering a linter for prose quality has its own cost, and a
  human should decide how far to go (existence-only check vs. structured front-matter vs. nothing,
  same as `research/wayfinder-process`'s Finding 3 tradeoff about enforcing QA-issue mechanics).

## Finding 4 — CLAUDE.md dropped the sibling's Boost MCP tool-usage guidance, keeping only config plumbing

The sibling's `laravel-prototype/CLAUDE.md:63-72` has a dedicated "Laravel Boost MCP" section
telling agents which tools to reach for and when: `search-docs` ("always run before coding"),
`database-query`, `database-schema`, `get-absolute-url`, `browser-logs`. Sprig's
`CLAUDE.md:80-85` "Agentic Tooling" section, by contrast, only describes what the config *is*:
"`boost.json` configures guidelines, MCP, and the `laravel-best-practices` skill. The MCP server
is wired in `.mcp.json` and runs via `docker compose exec -T app php artisan boost:mcp`" — no
mention of which Boost MCP tools exist or when an agent should use them instead of a manual
alternative.

This is a real content gap, not a stale reference needing removal (unlike the `.omc`/`develop`
references correctly stripped elsewhere — see the "headline verdict" section). The tools
themselves are unaffected by Sprig's Docker wrapper and would carry over directly.

- **Grounding**: observed today (direct section diff between the two `CLAUDE.md` files).
- **Severity**: Low-Medium. Doesn't break anything, but means agents working in this repo have no
  documented reason to prefer `search-docs`/`database-query`/etc. over ad hoc alternatives, which
  quietly reduces the odds Boost's MCP tools get used at all despite being fully wired and a real
  dependency (`composer.json:19`).
- **Triage: task.** No debate — re-add the adapted tool list (drop `sail`-specific framing since
  `boost.json:6` already sets `"sail": false` in both repos; keep the rest as-is, since Boost's MCP
  tool surface isn't project-specific).

## Finding 5 — "No AI attribution" is a convention that is actually holding (context, not a defect)

`CLAUDE.md:69` bans AI-attribution text in commits/PRs/issues. Checked across the full commit
history (`git log --all --grep`), the only "Co-authored-by" lines anywhere are dependabot's own
bot attribution (e.g. commit `c0b1dacab2...`, `Co-authored-by: dependabot[bot]
<49699333+dependabot[bot]@users.noreply.github.com>`) — zero instances of Claude/AI attribution in
37+ commits. Worth stating plainly since the map's tone calls for foregrounding risk, not padding
with praise: this is one convention in `CLAUDE.md` that is fully followed in practice, with
receipts, unlike the branch-naming and pre-commit-hook conventions sitting right next to it.

- **Grounding**: observed today.
- **Triage**: none — not a finding requiring action, cited only to keep the "is it actually
  followed" question honest in both directions.

## Summary table

| # | Finding | Grounding | Severity | Triage |
|---|---|---|---|---|
| — | Commands/MCP config are genuinely adapted, not blind-copied | Observed | — | No action (verdict) |
| 1 | Branch-naming convention abandoned for 5 of last 8 non-dependabot PRs; no ticket backing them | Observed | Medium | Task |
| 2 | Pre-commit hook documented but never enabled (`core.hooksPath` unset repo-wide) | Observed | Medium-High | Task |
| 3 | No CI/tooling enforcement of the per-domain `CLAUDE.md` mandate | Observed (today accurate) / Extrapolated (risk) | Medium | Grilling |
| 4 | Boost MCP tool-usage guidance dropped from `CLAUDE.md`, only config plumbing remains | Observed | Low-Medium | Task |
| 5 | "No AI attribution" convention verified clean across full commit history | Observed | — | No action (context) |

## Primary sources cited

- `CLAUDE.md` (worktree HEAD `98e581b`) — lines 27-38 (domain architecture definition), 64-77
  (Conventions for Agentic Work), 80-85 (Agentic Tooling)
- `boost.json`, `.mcp.json` (this repo and sibling)
- `.claude/commands/validate.md`, `ship.md`, `test.md`, `issue.md`, `openapi-audit.md`,
  `openapi-draft.md` (this repo and
  `/work/projects/marcth2/Laravel/Code/laravel-prototype/.claude/commands/*.md`, read-only)
- `.claude/skills/laravel-best-practices/**` — `diff -rq` against the sibling's copy: byte-identical
  (expected; generic Laravel guidance, not project-specific)
- `app/HealthCheck/CLAUDE.md`, `app/Http/CLAUDE.md`, `app/OpenApi/CLAUDE.md`
- `routes/api/health.php:8-9` — live routes cross-checked against `app/HealthCheck/CLAUDE.md:12-15`
- `.githooks/pre-commit`; `git config core.hooksPath` (empty)
- `composer.json:19`, `composer.lock:7499-7560` — `laravel/boost` real dependency confirmation
- PR [#22](https://github.com/marcth2/sprig-api-platform/pull/22) body — adaptation rationale
- PRs [#31](https://github.com/marcth2/sprig-api-platform/pull/31),
  [#32](https://github.com/marcth2/sprig-api-platform/pull/32),
  [#33](https://github.com/marcth2/sprig-api-platform/pull/33),
  [#35](https://github.com/marcth2/sprig-api-platform/pull/35),
  [#36](https://github.com/marcth2/sprig-api-platform/pull/36),
  [#37](https://github.com/marcth2/sprig-api-platform/pull/37) — branch names, absence of backing
  issues
- `gh issue list --state all` — confirms issues #1-#13 back the compliant early branches; no
  issues exist between #14 and #34 other than map #39's own children
- `git log --all --grep` — AI-attribution check across full history
