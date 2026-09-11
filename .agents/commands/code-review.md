---
description: Run code review on a PR or branch with CodeRabbit CLI and GOWA PHP SDK REVIEW.md Quality Gates
argument-hint: "[PR_NUMBER_OR_URL] [--base <branch>] [--post-comments]"
allowed-tools: "Bash(gh:*), Bash(git:*), Bash(coderabbit:*), Bash(composer:*), Bash(vendor/bin/*)"
---

# /code-review

Execute the `code-review` skill to audit the PR, branch, or local changes specified in **$ARGUMENTS**.

## Instructions

1. If no arguments are provided, inspect the current branch against `origin/main` or prompt the user for the PR/branch to review.
2. Rigorously follow the workflow defined in `REVIEW.md` and the `code-review` skill.
3. If the CodeRabbit CLI is installed and authenticated (`coderabbit auth status`), run `coderabbit review --committed --base <BASE> --agent` (or `--uncommitted --agent` for uncommitted local changes); otherwise, proceed seamlessly with the agent's autonomous audit engine based on the `REVIEW.md` Quality Gates.
4. Validate and filter all findings, discarding false positives caused by dirty working tree files.
5. Execute the SDK's local automated checks: `composer test` (Pest 3.x) and `composer lint` (PHP-CS-Fixer).
6. Present the report categorized by severity (Critical, Major, Minor, Suggestion) adhering to the `REVIEW.md` taxonomy.
7. If the `--post-comments` flag is provided or the user explicitly requests it, publish the review to GitHub using `gh api repos/:owner/:repo/pulls/:number/reviews`.
