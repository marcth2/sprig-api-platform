# Review: tooling & CI/CD config quality

Research ticket [#151](https://github.com/marcth2/sprig-api-platform/issues/151), part of map
[#148](https://github.com/marcth2/sprig-api-platform/issues/148).

Question: do `phpcs.xml`, `phpstan.neon`, `phpstan-bootstrap.php`, `pint.json`, `boost.json`,
`composer.json`, `.githooks/pre-commit`, `.github/workflows/*.yml`, `.github/CODEOWNERS`, and
`.github/dependabot.yml` hold up on correctness and redundancy — free of stale/contradictory
rules, free of drift from what `CLAUDE.md` documents, and free of unintentional inconsistency
between the pre-commit hook and CI? Every claim below cites a file path/line or command output —
no secondhand summarizing. `origin/master` HEAD is `74fe65e`; every file cited below was verified
directly against that ref with `git show origin/master:<path>` (not just the working tree, since
this checkout is shared with other concurrently-running agents).

## Headline verdict: the CI/permissions machinery from the #143/#145 incident holds up; the `/validate` command doesn't

`CLAUDE.md`'s "Known process gap" bullet documents a real prior incident: a `permissions:` block
on `wayfinder-close-gate.yml` didn't cover a scope a later check needed, and the map's own
close-gate silently no-opped as a result. Re-auditing that exact workflow today, the fix (PR #146)
is intact and correct — see "Nothing wrong here" below. But applying the same scrutiny elsewhere
surfaced a live version of the same failure class in a place nobody has looked since: the
`/validate` slash command CLAUDE.md itself documents has silently drifted to skip one of the six
mandatory gates, and CLAUDE.md's own prose already absorbed the drift as if it were correct. See
Finding 1.

## Finding 1 — `/validate` silently omits the `composer lint` gate that CLAUDE.md and CI both mandate

`CLAUDE.md`'s "Validation Gate" section states "All six gates must pass" and lists six commands,
including `docker compose exec app composer lint`. CI enforces all six —
`.github/workflows/ci.yml`'s "Run PHPCS" step runs `composer lint`.

But `.claude/commands/validate.md` — the actual command body — lists only **five** commands:
`composer test`, `composer analyse`, `composer format -- --test`,
`php artisan l5-swagger:generate`, `php artisan l5-swagger:audit --fail-on-warnings`.
`composer lint` is absent entirely, not merely reordered.

This isn't a one-off typo — the omission has already been absorbed as fact in two other places:

- CLAUDE.md's own "Agentic Tooling" section says "`/validate` runs the five-gate check",
  contradicting the "Validation Gate" section's "All six gates must pass" earlier in the same
  document.
- `.claude/commands/ship.md` says "Do not continue until all **five** gates pass" — `/ship`'s own
  step 1 is just "Run `/validate`", so it inherited the same undercount.

Net effect: an agent following `/validate` or `/ship` — the two commands CLAUDE.md's own
"Commands" bullet names as the way to close out implementation work — can mark a task complete
having never run PHPCS locally, then have CI's PHPCS step fail the PR days later. This is exactly
the "declared vs. actually checked" gap the #143/#145 incident is about, just in the Claude Code
tooling layer instead of a workflow's `permissions:` block.

- **Grounding**: `.claude/commands/validate.md` (five commands, `composer lint` absent, verified
  against `origin/master`); `CLAUDE.md` "Validation Gate" section (six gates, including
  `composer lint`); `CLAUDE.md` "Agentic Tooling" section ("five-gate check"); `.claude/commands/
  ship.md` ("all five gates"); `.github/workflows/ci.yml`'s "Run PHPCS" step (proving
  `composer lint` is load-bearing in CI, not vestigial).
- **Severity**: High. This is the one CI gate most likely to fail *after* a task is marked done,
  since it's the only one of the six with no equivalent check inside `/validate` at all (contrast
  with e.g. coverage, which `/validate`'s `composer test` step does cover).
- **Triage: task.** No debate — add `docker compose exec app composer lint` as a line to
  `.claude/commands/validate.md` (matching `CLAUDE.md`'s command exactly), and fix the two
  downstream "five" references (CLAUDE.md's "Agentic Tooling" section, `.claude/commands/ship.md`)
  to say "six".

## Finding 2 — `openapi-draft.md` still points at `app/OpenApi/`, which #136 folded into `app/Shared/`

CLAUDE.md's domain-architecture section documents: "`app/Shared/` is the other exception ...
Folded in from the former `app/OpenApi/` in [#136]." Confirmed against `origin/master`:
`git ls-tree origin/master:app` lists `Console`, `HealthCheck`, `Http`, `Models`, `Providers`,
`Shared` — no `OpenApi`.

`.claude/commands/openapi-draft.md` was never updated for the move: "Use `ref:` to reference
schemas defined by `#[OA\Schema]` on the owning DTO class (see `app/OpenApi/CLAUDE.md`) — do not
create standalone schema-holder classes in `app/OpenApi/`." Both paths in that sentence are dead.

- **Grounding**: `.claude/commands/openapi-draft.md`; `CLAUDE.md`'s domain architecture section
  (`app/OpenApi/` → `app/Shared/` fold-in, #136); `git ls-tree origin/master:app` (no `OpenApi/`,
  has `Shared/`).
- **Severity**: Low-Medium. `/openapi-draft` is documentation-generation guidance, not a gate —
  worst case is an agent citing a nonexistent file path to a developer, not a broken build.
- **Triage: task.** No debate — replace both `app/OpenApi/` references with `app/Shared/`.

## Finding 3 — Pre-commit's PHPStan/PHPCS scope silently diverges from CI's for any staged file outside `app/`, `config/`, `database/`, `routes/`, `tests/`

`.githooks/pre-commit` runs `phpstan analyse --memory-limit=-1 "${staged[@]}"` and
`phpcs "${staged[@]}"`, passing staged file paths as explicit CLI arguments. Passing explicit
paths to either tool overrides that tool's own configured path scope rather than narrowing within
it:

- `phpstan.neon` scopes analysis to `paths: [app]` only. `composer analyse` is invoked with zero
  path arguments both in the composer script and in CI's "Run PHPStan" step, so it only ever sees
  `app/`. But pre-commit's explicit-path invocation means a staged file *outside* `app/` — e.g.
  `bootstrap/providers.php`, the exact file CLAUDE.md's domain-architecture section names as where
  "domain-specific ServiceProvider(s)" get registered, and a file every new domain touches — gets
  analyzed locally by PHPStan even though `composer analyse` never analyzes it, in local dev or in
  CI.
- `phpcs.xml` scopes its `<file>` list to `app`, `config`, `database`, `routes`, `tests` —
  `bootstrap/`, `public/`, and `resources/` are outside it. `composer lint` (also CI's "Run PHPCS"
  step) is invoked with zero path arguments, so it's bound to that five-directory list.
  Pre-commit's explicit staged-path invocation again escapes this for the same
  `bootstrap/providers.php` case.

This is not the "fast local feedback + authoritative CI" pattern that's fine to leave alone — it's
the opposite: pre-commit's *effective* scope for PHPStan and PHPCS is a strict superset of CI's
for certain real files, purely as an accidental side effect of how each tool's CLI resolves
explicit paths vs. config-file paths. (Pint doesn't share this problem — `pint.json` declares no
path restriction, so an explicit-file invocation and a whole-repo invocation apply the same
ruleset either way.)

- **Grounding**: `.githooks/pre-commit`; `phpstan.neon` (`paths: [app]`); `phpcs.xml` (`<file>`
  list); `composer.json`'s `analyse`/`lint` scripts (no path args); `.github/workflows/ci.yml`'s
  "Run PHPStan"/"Run PHPCS" steps; CLAUDE.md's domain-architecture section (naming
  `bootstrap/providers.php` as a real, touched file). All verified against `origin/master`.
- **Severity**: Low-Medium. No defect has shipped from this yet (single domain, `bootstrap/
  providers.php` rarely edited so far) — surfaced by static analysis of the mechanism, not an
  observed failure.
- **Triage: grilling.** Two legitimate, opposite fixes exist, so this needs a human call: (a)
  narrow the pre-commit hook to only lint staged files that fall inside each tool's
  already-configured scope (accepts that `bootstrap/` etc. get zero static analysis, ever,
  matching CI exactly), or (b) decide `bootstrap/`, `public/`, `resources/` should actually be in
  scope for PHPStan/PHPCS and widen both `phpstan.neon`'s `paths` and `phpcs.xml`'s `<file>` list
  (and CI's expectations) to match. Which is correct depends on whether those directories are
  expected to carry enough custom logic to be worth level-max analysis — a real judgment call, not
  a bug with one obvious fix.

## Finding 4 — `dependabot.yml` has no `github-actions` ecosystem, despite the repo pinning several actions

`.github/dependabot.yml` declares three ecosystems: `composer`, `npm`, `docker` — each matching a
real manifest in the repo (`composer.json`, `package.json`, `Dockerfile`). Missing:
`github-actions`. The repo pins specific major versions of four actions across its three
workflows: `actions/checkout@v7` (in `ci.yml` and `wayfinder-release-checkpoint.yml`),
`docker/setup-buildx-action@v4` and `docker/build-push-action@v7` (both in `ci.yml`), and
`actions/github-script@v9` (in `wayfinder-close-gate.yml` and
`wayfinder-release-checkpoint.yml`). None of these get automated update PRs today; only
Composer/npm/Docker-base-image dependencies do.

- **Grounding**: `.github/dependabot.yml` (three ecosystems, verified against `origin/master`);
  action pins cited above, present in all three workflow files.
- **Severity**: Medium. Same risk class dependabot exists to cover for every other ecosystem in
  this repo (stale, unpatched dependency versions) — just uncovered for the one ecosystem that
  runs with repo-level `GITHUB_TOKEN` permissions.
- **Triage: task.** No debate — add a fourth `package-ecosystem: "github-actions"` block with
  `directory: "/"` and the same weekly schedule the other three use.

## Finding 5 — `ci.yml` has no `permissions:` block; both other workflows do

`.github/workflows/ci.yml` declares no top-level or job-level `permissions:` key anywhere (full
file read, confirmed with `grep -n permissions` against `origin/master`'s copy — no match), so its
`GITHUB_TOKEN` runs with whatever the repository's default token permissions are — not an
explicit, auditable minimum. Both other custom workflows in this repo already apply the
least-privilege lesson CLAUDE.md documents from #143/#145:
`wayfinder-close-gate.yml` declares `issues: write` + `pull-requests: read`, and
`wayfinder-release-checkpoint.yml` declares `contents: read` + `issues: write` — both scoped to
exactly what their scripts call (see "Nothing wrong here" below). `ci.yml` only checks out the
repo (needs `contents: read`) and runs everything else inside `docker run` containers with no
GitHub API calls — it needs nothing beyond `contents: read` and currently declares nothing at all.

- **Grounding**: `.github/workflows/ci.yml` (no `permissions:` key, full read + grep, both against
  `origin/master`); `wayfinder-close-gate.yml`, `wayfinder-release-checkpoint.yml` (their
  `permissions:` blocks); CLAUDE.md's "Known process gap" bullet.
- **Severity**: Low today (no step in `ci.yml` does anything an over-broad default token could
  abuse), but it's an inconsistency with a convention this repo has already paid a real cost to
  learn, sitting right next to the two workflows that learned it.
- **Triage: task.** No debate — add `permissions: contents: read` at the top of `ci.yml`.

## Finding 6 — Dead `pestphp/pest-plugin` entry in `composer.json`'s `allow-plugins`

`composer.json` lists `"pestphp/pest-plugin": true` under `config.allow-plugins`. Pest is not a
dependency anywhere: `composer.lock` has zero `pestphp/*` packages (`grep '"name": "pestphp'
composer.lock` → no matches), `composer.json`'s `require-dev` lists only `phpunit/phpunit`, and
`phpunit.xml` (not Pest config) is the project's test-runner config. This is inert config left
over from Laravel's installer template, which emits this entry regardless of PHPUnit-vs-Pest
choice at scaffold time.

- **Grounding**: `composer.json`'s `config.allow-plugins` block; `composer.lock` grep (no
  `pestphp` matches). Both verified against `origin/master`.
- **Severity**: Low. Inert, not misleading anyone about test-runner choice in practice, but it's
  dead weight in a file the review scope calls out by name ("`composer.json` ... scripts section
  especially").
- **Triage: task.** No debate — delete the line.

## Nothing wrong here

- **The #143/#145 permissions fix is intact.** `wayfinder-close-gate.yml`'s `pull-requests: read`
  is still present and still necessary — the GraphQL query in that workflow's script resolves
  `PullRequest.merged` on cross-referenced timeline items, which is exactly the call that silently
  failed before PR #146. No regression here.
- **`phpcs.xml`, `phpstan.neon`, `boost.json` all match CLAUDE.md's descriptions exactly.**
  `phpcs.xml`'s single `Generic.Files.LineLength` rule at 120 chars matches the "Validation Gate"
  section verbatim. `phpstan.neon`'s `level: max` and `reportUnmatchedIgnoredErrors: true` match
  the same section verbatim. `boost.json`'s `guidelines`/`mcp`/`skills` keys match the "Agentic
  Tooling" section's description of what the file configures.
- **`.github/CODEOWNERS`** (`* @marcth2`, with a comment about scoping by path once a second
  collaborator exists) matches CLAUDE.md's documented solo-maintainer, 0-required-approvals
  rationale from #76. Not stale.
- **The pre-commit-hook/CI duplication for Pint is genuinely fine**, not flagged: `pint.json` has
  no path restriction, so whole-repo (`composer format`) and explicit-staged-file
  (`.githooks/pre-commit`) invocations apply the identical ruleset to whatever they're pointed at
  — no scope drift, unlike Finding 3's PHPStan/PHPCS case.
- **`wayfinder-release-checkpoint.yml`'s `permissions:` block is exactly right** for what its
  script does — `contents: read` for `actions/checkout@v7` plus reading `CHANGELOG.md` off disk,
  `issues: write` for `issues.get`/`createComment`. Nothing declared that isn't used, nothing used
  that isn't declared.
- **`composer setup` already wires the pre-commit hook** (`git config core.hooksPath .githooks`
  is one of its steps) — this matches CLAUDE.md's pre-commit-hook bullet exactly, with no gap
  between documented and actual wiring.

## Summary table

| # | Finding | Grounding | Severity | Triage |
|---|---|---|---|---|
| — | #143/#145 permissions fix holds; `/validate` has a live version of the same failure class | Observed | — | No action (verdict) |
| 1 | `/validate` omits `composer lint`; CLAUDE.md itself echoes the undercount as "five gates" | Observed | High | Task |
| 2 | `openapi-draft.md` still references `app/OpenApi/`, folded into `app/Shared/` by #136 | Observed | Low-Medium | Task |
| 3 | Pre-commit's PHPStan/PHPCS scope is a superset of CI's for files outside 5 configured dirs | Observed (mechanism) | Low-Medium | Grilling |
| 4 | `dependabot.yml` has no `github-actions` ecosystem despite 4 pinned actions across 3 workflows | Observed | Medium | Task |
| 5 | `ci.yml` has no `permissions:` block, unlike the repo's other two workflows | Observed | Low | Task |
| 6 | Dead `pestphp/pest-plugin` entry in `composer.json` allow-plugins; Pest isn't a dependency | Observed | Low | Task |

## Primary sources cited

All verified directly against `origin/master` (HEAD `74fe65e`) via `git show origin/master:<path>`
and `git ls-tree`, not just the working tree, since this checkout is shared with other
concurrently-running agents:

- `CLAUDE.md` — Domain Architecture section (`app/OpenApi` → `app/Shared` fold-in), Validation
  Gate section (six commands), Conventions for Agentic Work section (branch protection/CODEOWNERS
  rationale; #143/#145 permissions incident), Agentic Tooling section
- `.claude/commands/validate.md`, `ship.md`, `openapi-draft.md`
- `phpcs.xml`, `phpstan.neon`, `pint.json`, `boost.json`, `composer.json`, `composer.lock`
- `.githooks/pre-commit`
- `.github/workflows/ci.yml`, `wayfinder-close-gate.yml`, `wayfinder-release-checkpoint.yml`
- `.github/CODEOWNERS`, `.github/dependabot.yml`
- `git ls-tree origin/master:app` — confirms `app/OpenApi/` absent, `app/Shared/` present
