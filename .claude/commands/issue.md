Start work on an existing GitHub issue by creating its branch.

Usage: `/issue <issue-number>`

1. Fetch the issue: `gh issue view <issue-number> --json title,body,state`.
2. Confirm it is open. If it is already closed, stop and ask before continuing.
3. Derive a slug from the issue title (lowercase, hyphens, no type prefix — this project's branch names are `<issue-number>-<slug>`, e.g. `9-agentic-tooling`).
4. Create and push the branch:
   ```bash
   git checkout master
   git pull --ff-only
   git checkout -b <issue-number>-<slug>
   ```
5. Do the work described in the issue body. Use `/ship` when it's ready to commit, push, and open the pull request (`Closes #<issue-number>`, targeting `master`).

This project has no `.omc/plans/` convention — issues are the persistent record of scope, created ahead of time under the relevant tracking issue.
