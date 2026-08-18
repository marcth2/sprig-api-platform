# Architecture Review: API Versioning & Release Mechanics

Research for [#42](https://github.com/marcth2/sprig-api-platform/issues/42), part of the [#39](https://github.com/marcth2/sprig-api-platform/issues/39) architectural review map.

## Summary

The `VERSION` file has claimed `APP_VERSION=1.0.0` since the moment it was created, in the same
commit that shipped the header-based versioning scaffold — before the repo had even cut its first
tag. Every git tag, GitHub Release, and `CHANGELOG.md` entry since then has used an entirely
separate `0.x.y` numbering scheme (currently `v0.1.2`). The two schemes have never been the same
number, not even briefly: they are two independent versioning systems that were bolted together
without reconciliation, not a value that "drifted" from a correct starting point. The project's own
`CLAUDE.md`, written after `VERSION` already said `1.0.0`, explicitly documents a policy that a real
`1.0.0` requires "a deliberate, separate decision" — meaning the file has been violating written
project policy since day one, only nobody has ever noticed hard enough to fix it.

## Finding 1 — `VERSION`'s `APP_VERSION=1.0.0` has never matched any tag, and contradicts CLAUDE.md's own policy

**Grounded in**: observed today (direct git history, not extrapolation).

**Evidence**:

- `VERSION` (repo root, lines 11–12) currently reads:
  ```
  APP_VERSION=1.0.0
  API_VERSION=${APP_VERSION}
  ```
- `VERSION` was introduced in a single commit, `9a96eb689adc1c3b5657ec923e6d0a9ce70e295f`
  ("Add API versioning scaffold (#16)", 2026-08-17), already containing `APP_VERSION=1.0.0`. That
  commit is the *only* commit in the repo's history that has ever touched the `VERSION` file
  (`git log --all --oneline -- VERSION` returns exactly one line). It has never been edited,
  including across the `v0.1.1` and `v0.1.2` patch releases that followed
  (`git diff v0.1.0 v0.1.2 -- VERSION` is empty).
- Every git tag in the repo is `0.x.y`: `v0.1.0` (`13b234e`, 2026-08-17 22:07:17 UTC), `v0.1.1`
  (`61f7ea4`, same timestamp), `v0.1.2` (`af5b082`, 2026-08-18 12:03:39 UTC) — confirmed via
  `git tag -l` and `git for-each-ref --sort=creatordate refs/tags`. `CHANGELOG.md` mirrors the same
  `0.1.0` / `0.1.1` / `0.1.2` numbering (`CHANGELOG.md:10,29,46`).
- Commit `9a96eb6` (VERSION created) is an ancestor of `v0.1.0` (`git merge-base --is-ancestor
  9a96eb6 v0.1.0` succeeds) — i.e. `VERSION` said `1.0.0` *before the very first tag was cut*, not
  after some later mishap. The mismatch was present at the first release, not introduced by drift.
- `CLAUDE.md`'s "Wayfinder → QA → release sequencing" section (added in commit `aeca3b3`, "Add
  v0.1.0 changelog entry and document the release ritual (#30)" — which comes *after* `9a96eb6` in
  history) states in plain text: *"reserve an actual `1.0.0` bump for a deliberate, separate
  decision that the API is a stable public contract."* That sentence was written into the repo
  while `VERSION` already contained `APP_VERSION=1.0.0` — the contradiction wasn't caught even at
  the moment the policy prohibiting it was documented.
- `config/app.php:47` wires `VERSION`'s `APP_VERSION` into Laravel's own `config('app.version')`
  (`'version' => env('APP_VERSION', '0.0.0-unversioned')`), and `config/api.php:6` wires it into
  `config('api.version')` (`'version' => env('API_VERSION', '1.0.0')`). Both configs, plus
  `config/l5-swagger.php:336` (`L5_SWAGGER_CONST_VERSION`), read the same tainted value. That means
  the **publicly generated OpenAPI spec's `info.version` field reports `1.0.0`** even though the
  software shipping it is `v0.1.2` — this isn't an internal bookkeeping nit, it's a wrong number in
  a document external API consumers read.

**Why it happened**: `VERSION`, `config/api.php`, and the `ApiVersion` middleware were copied
near-verbatim from the reference sibling `laravel-prototype`
(`/work/projects/marcth2/Laravel/Code/laravel-prototype/VERSION` is byte-for-byte identical, down to
the same `APP_VERSION=1.0.0` placeholder and the same semver comment block; its `config/api.php` is
identical except for the `vendor` string). The prototype has no `CHANGELOG.md` and no git-tag/GitHub
Release ritual in its `CLAUDE.md` — it tracks progress via numbered "phases" in `docs/agentic/BUILD.md`
instead, so its own placeholder `1.0.0` never collides with anything. Sprig then layered a completely
separate, *new* numbering convention on top — the wayfinder-driven `0.x.y` git-tag + `CHANGELOG.md` +
GitHub Release ritual documented in `CLAUDE.md`'s "Release mechanics" section — without going back to
reconcile the copied `VERSION` scaffold's placeholder value against it. The two systems were designed
by different projects for different purposes and never integrated.

**Severity**: High. This is a single-source-of-truth file (per `CLAUDE.md`: *"VERSION file: VERSION
… is the single source of truth for APP_VERSION and API_VERSION"*) that has been wrong in every
release to date, feeds a customer-facing OpenAPI document, and directly contradicts a policy
sentence sitting in the same file that would forbid it if anyone read both at once.

**Triage recommendation**: **task**. There is no genuine tradeoff here — `VERSION` should read
`APP_VERSION=0.1.2` (or whatever the next release will be) today, and updating it should become a
required step in the release ritual `CLAUDE.md` already documents (the "Release mechanics" section
lists tag/CHANGELOG/GitHub-Release steps but never mentions updating `VERSION` — that step is simply
missing from the checklist). Fixing the value and adding the missing step to the ritual is
mechanical, not contested.

## Finding 2 — The header-negotiated major version and `config('api.supported_versions')` are two independently-maintained numbers with no coupling check

**Grounded in**: reasoned extrapolation to more domains/versions landing (not yet observable as a
bug today, because nobody has bumped anything yet).

**Evidence**:

- `app/Http/Middleware/ApiVersion.php:36-52` (`resolveVersion`) falls back, when no `X-API-Version`
  header or vendor `Accept` media type is present, to `(string) explode('.', config('api.version',
  '1.0.0'))[0]` — i.e. it derives the *default* major version by taking the leading digit of
  `config('api.version')`, which is itself `env('API_VERSION', '1.0.0')` (`config/api.php:6`),
  which is `VERSION`'s `APP_VERSION` (transitively, via `config/app.php:12`'s `Dotenv::create(...,
  'VERSION')` load).
- Separately, `ApiVersion.php:21-29` validates the resolved version against
  `config('api.supported_versions', ['1'])`, a hardcoded array literal in `config/api.php:8`
  (`'supported_versions' => ['1']`) — a plain string list with no derivation from `VERSION` at all.
- These are two independently hand-maintained representations of "what major version is this API
  on." Today they happen to agree (`1.0.0` → major `1`, and `['1']` lists `1`), so the fallback path
  works and the `ApiVersionTest` suite (`tests/Unit/Http/Middleware/ApiVersionTest.php:34-41`,
  `tests/Feature/Http/Middleware/ApiVersionTest.php:60-73`) passes. But there is no test and no
  guard coupling them. If a future breaking change bumps `VERSION`'s `APP_VERSION` to `2.0.0` (per
  the semver policy documented in `VERSION:6-9`: *"Major (1.x.x → 2.0.0) breaking API contract
  change; X-API-Version header bumps (v1 → v2)"*) without someone remembering to also add `'2'` to
  `config/api.php`'s `supported_versions` array, every unheadered request — the documented default
  path — would immediately start receiving `406 Not Acceptable` (`ApiVersion.php:24-29`) the moment
  that `VERSION` change ships, because the *fallback* version and the *supported* list would
  silently diverge.
- This is currently invisible because the project has never gone through an actual major bump —
  it's a structural risk in the design, not a bug that has fired yet.

**Severity**: Medium (today: latent/theoretical; will become real and immediate the first time a
major version bump happens, likely at domain #2 or #3 per the map's "more domains landing" lens).

**Triage recommendation**: **grilling**. This is a genuine design tradeoff, not a clear-cut fix.
Options include: (a) derive `supported_versions` from `VERSION`'s major digit plus an explicit
deprecation list, (b) keep them separate but add a boot-time/test assertion that the current default
major is always present in `supported_versions`, or (c) leave them decoupled deliberately, since
supporting multiple simultaneous majors (e.g. `['1', '2']` during a deprecation window) is exactly
the scenario a single derived value can't express. Which of these is right depends on how the
project intends to run major-version deprecation windows — a decision nobody has had to make yet
with only one domain live, and worth a human call rather than a unilateral fix.

## Other observations (not separately triaged — folded into Finding 1's fix)

- The semver policy text in `VERSION:6-9` is otherwise internally consistent and matches actual
  middleware behavior (major bump ↔ header version bump, confirmed by `ApiVersion.php` and its
  tests) — the *policy* isn't wrong, only the *value* sitting above it.
- `app/HealthCheck/README.md:97,160` document `api_version` example values as `1.0.0`, sourced from
  `app/HealthCheck/Checks/ApplicationHealthCheck.php:50` (`'api_version' => config('api.version')`).
  These are illustrative doc examples, not a second bug — they'll self-correct once Finding 1 is
  fixed, since they read from the same config.
- `config/l5-swagger.php:336` also defaults `L5_SWAGGER_CONST_VERSION` to `'1.0.0'` — same root
  cause as Finding 1, not a separate defect.

## Reference comparison: `laravel-prototype`

`/work/projects/marcth2/Laravel/Code/laravel-prototype` (read-only reference, outside this
worktree) has a byte-for-byte identical `VERSION` file (same `APP_VERSION=1.0.0` placeholder, same
semver comment) and a `config/api.php` that differs from Sprig's only in the `vendor` string
(`laravel-prototype` vs `laravel-platform`). Critically, the prototype has **no `CHANGELOG.md`** and
its `CLAUDE.md` describes no git-tag/GitHub-Release ritual — progress is tracked via "Phase 8
(complete)" narrative in `docs/agentic/BUILD.md` instead. So the prototype's own unbumped `1.0.0`
placeholder never collides with a competing numbering scheme, because the prototype never built one.
Sprig inherited the placeholder and then, independently, invented the git-tag + CHANGELOG + GitHub
Release ritual (`CLAUDE.md`'s "Release mechanics" section) — the collision is a Sprig-only problem
introduced by combining two conventions that were never designed to coexist. This is a useful
"unexplained divergence" data point per the map's Notes, not a case where the prototype should be
treated as ground truth to copy back from.
