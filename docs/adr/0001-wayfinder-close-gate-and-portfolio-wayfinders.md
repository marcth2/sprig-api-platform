# Technically enforce wayfinder→QA→release sequencing via a self-healing close-gate, with a portfolio/build wayfinder split

**Status:** accepted

The wayfinder→QA→release sequencing rule (CLAUDE.md) had zero technical enforcement, and was violated in its first real cycle: wayfinder #14 closed ~2h before QA issue #34 (meant to gate it) even existed. We're adding a GitHub Action, triggered on `issues.closed` for `wayfinder:map` issues, that checks whether every child ticket is closed and immediately reopens the wayfinder (with an explanatory comment) if not. Because it acts on the event regardless of who closed the issue, it can't be bypassed the way branch-protection review already is (self-approval blocked → admin bypass). A second, non-destructive Action on tag/release push detects — but does not auto-remediate — a release cut before its wayfinder closed, since releases are immutable on this repo.

The existing QA mechanism anchors a manual QA issue to the single commit SHA that closed the last ticket — designed for a **build wayfinder** (many tickets converging on one integrated deliverable, e.g. #14's HealthCheck domain). That pattern doesn't fit a **portfolio wayfinder** like #39 (largely independent decisions/fixes, each already individually gated — a task ticket by its own PR+CI against real mariadb/redis containers, a grilling ticket by live conversation) — there's no single closing commit, and no integrated artifact that only becomes verifiable once everything lands together. So wayfinders now declare their type via an explicit label (`wayfinder:portfolio` or `wayfinder:build`, alongside `wayfinder:map`) set at charting time, and the close-gate applies a different completeness rule per type:

- **`wayfinder:build`**: unchanged — requires a QA issue anchored to the final commit SHA, added as a child.
- **`wayfinder:portfolio`**: no aggregate QA issue required. The gate checks per-child completeness instead — every code-touching task ticket needs a merged PR, every grilling ticket needs a resolution comment.

## Considered Options

- **No enforcement** — rejected: the failure already happened once, in the very first cycle.
- **Detect-and-flag only, no self-healing** — rejected for the close-checkpoint: it would report the #14/#34-style violation after the fact but not prevent it. Kept for the release-checkpoint, since a published release/tag is immutable here — auto-reverting it is destructive, so detection is the appropriate rigor there.
- **Hard infra gate** (restrict who/what can push tags or publish releases) — rejected as disproportionate infrastructure and maintenance cost for a solo-maintainer repo with a low release cadence.
- **Infer wayfinder type automatically** (from Notes text or ticket composition) instead of an explicit label — rejected as too fragile; the gate needs a deterministic, machine-readable signal.

## Consequences

Every future map must be labeled `wayfinder:portfolio` or `wayfinder:build` at charting time, or the close-gate has nothing to key off. Map #39 is retroactively labeled `wayfinder:portfolio`.
