# Review: wayfinder → QA → release sequencing (CLAUDE.md)

Research ticket [#43](https://github.com/marcth2/sprig-api-platform/issues/43), part of map [#39](https://github.com/marcth2/sprig-api-platform/issues/39).

Question: is the wayfinder→QA→release sequencing rule in `CLAUDE.md` well-designed, enforceable,
and actually followed? Every claim below is sourced to a commit SHA, issue/PR number, or file
path — no secondhand summarizing.

## The rule as it stands today

`CLAUDE.md`, "Wayfinder → QA → release sequencing" bullet (current text, worktree HEAD):
a wayfinder map does not close when its tickets close; order is (1) tickets closed, (2) a QA
issue opened and added to the wayfinder's own checklist, anchored to the commit SHA that closed
the last ticket, with comment-logged real command/output evidence, (3) QA issue closes clean →
wayfinder closes, (4) only then is the release cut. The file's own revision footer dates this to
**2026-08-18**, replacing a **2026-08-17** version that only gated the *release* on QA, not the
wayfinder's own close.

## What actually happened — the v0.1.1 gap, reconstructed from raw timestamps

All times below are from `git log --format=%ai` (tag commits) and `gh issue/pr view --json
createdAt,closedAt` / `gh api .../timeline` (GitHub events), converted to UTC:

| Time (UTC) | Event |
|---|---|
| 2026-08-17T21:06:36Z | Wayfinder #14 **closed** (all 13 tickets done) |
| 2026-08-17T21:16:30Z / :45Z | `v0.1.1` tagged and GitHub Release published |
| 2026-08-17T23:08:41Z | QA issue **#34 opened** — anchored to `1e219df`, nearly 2 hours *after* the release it was meant to gate |
| 2026-08-18T00:36:59Z | #34 comment: mariadb health check found broken (`DB_CONNECTION=sqlite` in `.env.example` vs. a real `mariadb` service) |
| 2026-08-18T00:39:27Z–01:07:54Z | PR [#35 — "Fix mariadb health check: point DB_CONNECTION at the mariadb service"](https://github.com/marcth2/sprig-api-platform/pull/35) opened and merged (`08960fc`) |
| 2026-08-18T01:42:56Z–01:49:27Z | PR [#36 — "Make the QA issue gate a wayfinder's own close, not just the release"](https://github.com/marcth2/sprig-api-platform/pull/36) opened and merged (`fd79206`, tree state `3f106b4`) — the process fix itself |
| 2026-08-18T01:43:17Z | #14 **reopened** |
| 2026-08-18T01:49:22Z | #34 closed clean (full checklist re-run against `3f106b4`) |
| 2026-08-18T01:49:40Z | #14 closed again |
| 2026-08-18T12:03:40Z | `v0.1.2` released |

So the actual defect wasn't "release came 10 minutes before QA" in some narrow technical sense —
it's that **no QA issue existed at all** at the moment either the wayfinder closed or the release
shipped. QA was invented retroactively, after the release was already public, because nothing in
the process up to that point required it to exist first. `CLAUDE.md`'s own footer and PR #36's
body confirm this reading: "the v0.1.1 release predated the opening of its QA issue (#34)... which
predated wayfinder #14 closing" — i.e., every step (tickets closed → wayfinder closed → release
cut → QA issue opened → defect found → defect fixed → process doc fixed → wayfinder reclosed) ran
in a different order than the rule (once it existed) says it should.

**Grounded in what's observably true today.** This is not extrapolation — it's the one completed
wayfinder cycle this repo has.

## Finding 1 — The sequencing rule has zero technical enforcement

Nothing checks it. There's no CI job, branch-protection rule, or `gh` script that blocks a
`git tag`/`gh release create` unless a linked QA issue is closed, the way the five validation
gates (`composer test`, `composer analyse`, `composer format -- --test`, `l5-swagger:generate`,
`l5-swagger:audit`) are mechanically enforced by `.github/workflows/ci.yml` on every push. The
wayfinder rule is prose in `CLAUDE.md` that depends entirely on whoever (human or agent) is
cutting a release remembering to read it and comply.

The proof this isn't hypothetical: the rule was violated in its very first real cycle, by the same
solo maintainer/agent who wrote it, on the same day it existed in its pre-#36 form. A process
whose only enforcement mechanism is "the agent read the doc" produced a violation before the ink
dried.

- **Grounding**: observed today — one full cycle (#14/#34/v0.1.1/v0.1.2), one violation.
- **Severity**: High. This is exactly the "load-bearing" foundational piece map #39's Notes call
  out for priority ("stabilize load-bearing pieces (versioning truth, the wayfinder process
  itself)"), and it currently has the same enforcement strength as a comment.
- **Triage: grilling.** Adding a technical gate (e.g., a `gh` pre-release check, or a CI job that
  refuses to tag if any open wayfinder has an unclosed/unlinked QA issue) is a real design
  question with real cost — this is a single-maintainer, single-agent project today, and machinery
  built to police one's own future self has its own maintenance and false-positive cost. Worth a
  human decision on whether prose discipline is acceptable at this scale, or whether it becomes
  wrong specifically as more domains/wayfinders land (extrapolated risk layered on the observed one).

## Finding 2 — PR #36 (the fix) was self-authored, self-reviewed, and self-merged with zero review

`gh pr view 36 --json reviews,author,mergedBy` shows `"reviews":[]` and identical `author`/
`mergedBy` (`marcth2`). The same actor who designed the original rule, who violated it, who found
the violation, and who wrote the fix is also the sole approver of the fix. This mirrors exactly
the independence gap in the QA process itself (Finding 4) — there is no second party anywhere in
this loop, by construction (branch protection on `master` per issue #14's Notes requires "PR + 1
approval" but explicitly lists `marcth`/`marcth2` as **bypass-capable admins**, so self-merge
without review is sanctioned, not a slip).

- **Grounding**: observed today (PR #36's own metadata).
- **Severity**: Medium. Consistent with a solo-maintainer repo's stated bypass policy from #14, so
  it isn't a violation of a stated rule — but it means the fix to the process-integrity problem was
  itself produced with the same lack of independent check that let the original gap happen.
- **Triage: task** to at least note this explicitly rather than silently accept it — no real
  debate that self-review-of-the-reviewer is worth flagging; whether to *change* bypass policy for
  a solo project is arguably not a real decision to make right now (nobody else exists to review),
  so this is really just "record the gap," not a design fork. If it's instead treated as tied to
  Finding 4's independence question, fold it into that grilling ticket instead of a separate task.

## Finding 3 — "anchor the QA issue to the commit SHA that closed the last ticket" breaks down for non-code wayfinders

The rule's mechanics (SHA-anchor a QA issue, verify real-environment integration seams) were
designed against #14 — a wayfinder whose tickets are 100% code-shipping (each ticket closes via a
merged PR against a commit). Map #39 (this very map) is a **research/architecture-review**
wayfinder: its 7 child tickets (#40–#46) are findings/analysis, several producing only Markdown
files on throwaway research branches (per this ticket's own instructions — this file is pushed to
`research/wayfinder-process`, never merged to `master`). Some tickets under #39 may spawn follow-on
`wayfinder:task` tickets that *do* ship code (map #39's Notes: "a 'change' verdict is expected to
spawn... the task that actually implements it"), but the map itself has no single commit SHA that
"closed the last ticket" in the sense #14 did — closure here is a mix of comment-and-close research
tickets and separately-merged remediation PRs.

`CLAUDE.md`'s sequencing text has no branch for this. It's silent on:
- What "QA" even means for a wayfinder whose deliverable is analysis rather than running code (the
  "Testing philosophy" bullet defines manual-QA scope only in terms of "real-environment
  integration seams" and "shared/cross-cutting code the wayfinder touched" — neither concept maps
  cleanly onto a set of Markdown research findings).
- Which SHA to anchor to when the map's tickets close across multiple unrelated branches/PRs
  that never merge to `master` together, or don't merge to `master` at all.

- **Grounding**: this is **extrapolated**, not yet observed as a failure — map #39 hasn't reached
  its own close-sequencing decision yet. But it's not speculative-far-future extrapolation either:
  it's the *very next* wayfinder cycle this repo will run, using the exact rule under review.
- **Severity**: High for #39 specifically (it will hit this gap directly, soon), Medium as a
  general finding (future domain wayfinders will likely be code-shipping like #14, so this gap is
  really about research-wayfinders and mixed hybrid wayfinders like #39).
- **Triage: grilling.** There's a real tradeoff between (a) writing a second sequencing variant for
  research/hybrid wayfinders, (b) stretching the existing rule's language to cover both cases with
  looser wording, or (c) deciding research wayfinders like #39 don't need a QA-issue gate at all and
  close on ticket-completion alone (since there's no running system to verify). All three are
  defensible; this needs a decision, not a patch.

## Finding 4 — No independence between implementer and QA verifier

Issue #34's checklist was authored, executed, and closed by the same actor (`marcth2`) who also
authored the fix it validated (PR #35) and who runs the whole repo. The QA issue's evidence quality
is genuinely good — real commands, real output, a caught defect (the mariadb `.env.example`
misconfiguration), and an honest correction of the checklist's own wrong assumption (the `/up`
maintenance-mode item, corrected in the 2026-08-18T01:49:14Z comment rather than glossed over).
That's the process working as designed on the evidence-quality axis. But "real evidence over a
rubber-stamp" (CLAUDE.md's own phrase) is about evidence quality, not about who is allowed to
grade their own homework — there is no second reviewer anywhere in the #34 lifecycle, matching
Finding 2's pattern.

- **Grounding**: observed today, in the one completed QA cycle.
- **Severity**: Medium today (single domain, low stakes, solo maintainer); this is the finding most
  likely to compound as more domains land, since each new domain's QA issue will follow the exact
  same unreviewed self-close pattern by default, with no doc language anywhere suggesting it should
  change.
- **Triage: grilling.** Same shape as Finding 1/2 — real cost/benefit tradeoff for a solo project;
  worth a deliberate decision (e.g., "acceptable while solo, revisit at N domains or first external
  contributor") rather than silent drift.

## Finding 5 — Defect-fix PRs found during QA aren't linked back to the wayfinder

The redesigned rule's stated goal (PR #36 body, CLAUDE.md text) is making the wayfinder↔QA
relationship "GitHub-native and discoverable from either issue, not just documented in prose."
That's achieved for the QA issue itself — #34 is on #14's checklist, and #34's body/comments
link back to #14. But PR #35 (the actual mariadb defect fix that #34's QA run required before it
could close clean) is **not** added to #14's checklist and is not referenced anywhere in #14's
issue body — it's only discoverable by opening #34 and reading its comments, which link to PR #35.
Anyone scanning #14's checklist top-to-bottom sees 13 tickets + #34 and would not know a
mid-flight defect (and its fix) existed at all without drilling into #34's comment thread.

- **Grounding**: observed today (#14's issue body, fetched via `gh issue view 14`, lists only
  tickets #1–#13 and #34; PR #35 appears nowhere in it).
- **Severity**: Low-Medium. Doesn't break the gate mechanically (QA still gates correctly), but
  undercuts the stated discoverability goal the rewrite was explicitly trying to achieve.
- **Triage: task.** This is clear-cut — the fix is "also cross-link defect-fix PRs discovered
  during a wayfinder's QA pass onto the wayfinder's own checklist," which nobody would seriously
  argue against. No real tradeoff to grill.

## Finding 6 — The "patch = between wayfinders" framing doesn't match either real patch example

`CLAUDE.md`: "Patches are reserved for out-of-band fixes between wayfinders (e.g. v0.1.1) and are
never tied to a wayfinder completion." But both real patches so far contradict "between":
- **v0.1.1** (tagged 2026-08-17T21:16Z) shipped 10 minutes after wayfinder #14's *first* close and
  nearly 2 hours *before* QA issue #34 even existed — i.e., squarely inside #14's still-unresolved
  QA gate, not in a gap after #14 was legitimately done and before some future wayfinder started.
- **v0.1.2** (CHANGELOG.md: "Patch release fixing a defect found during manual QA of the
  HealthCheck domain (issue #34)") is, by the CHANGELOG's own words, the release that resolves
  wayfinder #14's QA findings — again tied directly to a wayfinder's completion, not "between"
  wayfinders in any temporal sense.

The patch-vs-minor compatibility logic ("judged by the wayfinder's actual compatibility impact")
is fine on its own terms — both patches are correctly patch-level by compatibility impact. The
problem is narrower: the rule's own illustrative example (`v0.1.1`) and vocabulary ("between
wayfinders") don't describe either real-world instance, so a future reader has no accurate mental
model of what "between wayfinders" is supposed to mean from the only examples on record.

- **Grounding**: observed today (CHANGELOG.md entries for v0.1.1/v0.1.2 vs. the timestamps above).
- **Severity**: Low. Cosmetic/terminology confusion, not a functional gap — nothing was released at
  the wrong version number.
- **Triage: task.** Reword the bullet's example/definition to match reality (e.g., "a patch may
  land while a wayfinder's QA gate is still open, provided it doesn't itself become the thing being
  QA'd without its own verification" — or simply drop the "(e.g. v0.1.1)" example since it no
  longer illustrates what it's attached to). Not a real debate — just an inaccurate example to fix.

## Reference-sibling comparison

`/work/projects/marcth2/Laravel/Code/laravel-prototype/CLAUDE.md` (75 lines, read-only, outside
this worktree) contains **zero** occurrences of "wayfinder," "QA," "release," "changelog," or
"sequenc[ing]" (checked via `grep -in`). The prototype — 8 phases complete, the more mature
sibling — has no equivalent governance layer at all: no map/ticket/QA/release ordering rule of any
kind is documented there.

That absence is informative in one direction and not in another. It's evidence that this entire
wayfinder→QA→release apparatus is a **novel invention specific to Sprig**, not a carried-over,
battle-tested convention from the more advanced reference project (unlike the domain architecture,
validation gates, and versioning scheme, which map #39's Notes and issue #14's Notes confirm *were*
carried over deliberately). It is not evidence that the apparatus is unnecessary — the prototype's
silence on process governance could equally mean the prototype never needed one yet, was built by
different means, or simply predates this concern. Filed as context, not as a verdict either way.

## Summary table

| # | Finding | Grounding | Severity | Triage |
|---|---|---|---|---|
| 1 | Zero technical enforcement of the sequencing rule | Observed | High | Grilling |
| 2 | Fix PR (#36) self-authored/self-reviewed/self-merged | Observed | Medium | Task |
| 3 | SHA-anchor/QA mechanics don't cover research/hybrid wayfinders (hits map #39 next) | Extrapolated (imminent) | High (for #39) / Medium (general) | Grilling |
| 4 | No independence between implementer and QA verifier | Observed | Medium | Grilling |
| 5 | Defect-fix PRs found during QA don't link back to the wayfinder checklist | Observed | Low-Medium | Task |
| 6 | "Patch = between wayfinders" example contradicts both real patches (v0.1.1, v0.1.2) | Observed | Low | Task |

## Primary sources cited

- `CLAUDE.md` (worktree HEAD, commit `98e581b`) — "Wayfinder → QA → release sequencing" and
  "Testing philosophy" bullets, revision footer
- PR [#36](https://github.com/marcth2/sprig-api-platform/pull/36) — diff, body, commit
  `3f106b4`/`fd79206`, zero reviews
- PR [#35](https://github.com/marcth2/sprig-api-platform/pull/35) — mariadb fix, commit `08960fc`
- Issue [#14](https://github.com/marcth2/sprig-api-platform/issues/14) — map, Notes, 2026-08-18
  correction, checklist
- Issue [#34](https://github.com/marcth2/sprig-api-platform/issues/34) — QA checklist and comments
  (`2026-08-18T00:36:59Z` failure report, `2026-08-18T01:49:14Z` clean pass)
- `CHANGELOG.md` — v0.1.0/v0.1.1/v0.1.2 entries
- `git log` tag commits: `v0.1.0` = `aeca3b3` (2026-08-17T17:05:54-04:00), `v0.1.1` = `d8a681b`
  (2026-08-17T17:16:30-04:00), `v0.1.2` = `98e581b` (2026-08-18T08:03:27-04:00)
- GitHub Releases API (`gh api repos/marcth2/sprig-api-platform/releases`) — `v0.1.1`
  `published_at=2026-08-17T21:16:45Z`, `v0.1.2` `published_at=2026-08-18T12:03:40Z`
- Issue timeline API (`gh api repos/.../issues/14/timeline`) — closed `2026-08-17T21:06:36Z`,
  reopened `2026-08-18T01:43:17Z`, closed `2026-08-18T01:49:40Z`
- `/work/projects/marcth2/Laravel/Code/laravel-prototype/CLAUDE.md` (read-only, outside this
  worktree) — grepped for wayfinder/QA/release/changelog/sequencing terminology, zero hits
