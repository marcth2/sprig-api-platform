Validate, then present a review summary to the developer before committing, pushing, and opening a pull request.

**Never commit without explicit developer confirmation.**

---

## Step 1 — Validate

Run `/validate`. If any gate fails, stop and report. Do not continue until all six gates pass.

---

## Step 2 — Present review summary

After all gates pass, display the following together in a single response for the developer to review:

### Test results

Extract and show the key numbers from the test run output:
- Number of tests passed / total
- Number of assertions
- Coverage percentage

Example format:
```
Tests:  52 passed (312 assertions)
Coverage: 100%
```

### Git status

Run and display:
```bash
git status
git diff --stat HEAD
```

### Proposed commit message

Find the open GitHub issue linked to this branch. Look for an issue number in the branch name, or check recent open issues with `gh issue list`. Draft a commit message:

```
<imperative summary of the work>
```

Keep the summary under 72 characters. Do not include AI attribution.

---

## Step 3 — Wait for developer confirmation

After presenting the review summary, stop and ask:

> Ready to commit? Review the test results, changed files, and proposed commit message above.
> Reply with:
> - **"yes"** or **"ship it"** to proceed
> - A revised commit message to use instead
> - **"no"** or **"wait"** to abort

Do not proceed until the developer explicitly confirms.

---

## Step 4 — Commit, push, and open PR (only after confirmation)

Once confirmed:

1. **Stage and commit** (be specific — do not use `git add .`):
   - Never stage: `.env`, `storage/`, `bootstrap/cache/`
   ```bash
   git add <relevant files>
   git commit -m "<confirmed commit message>"
   ```

2. **Push**:
   ```bash
   git push -u origin <current-branch>
   ```

3. **Open PR** targeting `master` (the only long-lived branch — this project uses a trunk-based workflow, never `develop`):
   ```bash
   gh pr create --title "<issue title>" --body "Closes #<issue-number>"
   ```

Report the PR URL when done.
