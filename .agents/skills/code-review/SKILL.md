---
name: code-review
description: Executes a complete code review of a PR, branch, or local changes against the GOWA PHP SDK REVIEW.md. Integrates CodeRabbit CLI (--agent), multi-axis auditing (PHP 8.2 Compatibility & Typing, Security & Anti-SSRF, Resilient Parsing, and Pest Testing), local automated validation (composer test and composer lint), filters false positives, and posts detailed review comments to GitHub PRs via gh. Trigger whenever the user asks to "do code review", "review PR", "run coderabbit on PR", "code review", or when validating branches before merge.
---

# Code Review Skill — GOWA PHP SDK

This skill acts as the **guardian against bugs, compatibility breaks, performance regressions, and security vulnerabilities** in the GOWA PHP SDK (`gowa-php`).
It consolidates project guidelines (`REVIEW.md`, `AGENTS.md`), runs the **CodeRabbit CLI (`--agent`)** engine, validates code using local tools (`composer lint`, `composer test`), audits changes across multiple axes, and optionally publishes review comments directly to GitHub via `gh`.

---

## Execution Flow

```
[1. Resolve Target] ──> [2. Gather Context & Spec] ──> [3. Execute CodeRabbit CLI]
                                                                  │
[6. Publish / Report] <── [5. High-Signal Filtering] <── [4. Multi-Axis Audit & Tests]
```

---

## Step 1: Resolve Target and Prepare Diff

Identify what needs to be reviewed based on the user's input:

1. **Pull Request (e.g., `PR #7` or URL):**
   ```bash
   gh pr view <PR> --json number,title,body,baseRefName,headRefName,headRefOid,commits
   ```
   - Identify the base commit and the HEAD commit SHA of the PR.
   - Obtain the exact diff:
     ```bash
     git diff origin/<BASE_BRANCH>...<HEAD_SHA>
     ```

2. **Local Branch vs Base (e.g., `feat/...` against `main`):**
   ```bash
   git log origin/main..HEAD --oneline
   git diff origin/main...HEAD
   ```

3. **Uncommitted Local Changes (Work in Progress):**
   ```bash
   git diff
   ```

---

## Step 2: Context, Spec, and Guidelines

Load the necessary references:

1. **Project Guidelines:**
   - Read `REVIEW.md` (severity rules and SDK-specific quality gates).
   - Check `AGENTS.md` for architectural rules and repository conventions.

2. **Spec / Intent Identification:**
   - If reviewing a PR: read the full PR description and any linked issues (`Refs #...` or `Closes #...`).
   - The review must verify whether the implementation **faithfully fulfills the original intent** without leaving loose ends or regressions.

---

## Step 3: CodeRabbit CLI (with Transparent Native Fallback)

Before calling CodeRabbit, verify whether the CLI is installed and authenticated:

```bash
if command -v coderabbit >/dev/null 2>&1 && coderabbit auth status >/dev/null 2>&1; then
    echo "CODERABBIT_READY"
else
    echo "CODERABBIT_UNAVAILABLE"
fi
```

### Scenario A: CodeRabbit Installed and Authenticated
Run the CodeRabbit CLI in structured agent mode (`--agent`):
```bash
# For committed branch/PR changes:
coderabbit review --committed --base <BASE_BRANCH> --agent

# For uncommitted local edits:
coderabbit review --uncommitted --agent
```
Collect the emitted findings to cross-reference during the multi-axis audit.

> **IMPORTANT — Watch Out for Local Working Tree State:**
> The CodeRabbit CLI reads the local file system. If uncommitted changes exist in the workspace that are not part of the PR under review, filter out and discard any findings caused by those unrelated local edits.

### Scenario B: CodeRabbit Absent or Unauthenticated (Automatic Fallback)
- **NEVER halt or fail the review.**
- The agent assumes **100% of the audit autonomously**, analyzing the diff directly against the `REVIEW.md` quality gates in Steps 4 and 5.
- Simply include an informational note in the final report footer:
  > ℹ️ *CodeRabbit CLI was not detected or not authenticated in this environment. The review was successfully conducted by the agent's native Quality Gates engine.*

---

## Step 4: Local Automated Checks

Before concluding the analysis, run local mechanical checks:

```bash
# 1. Check style and syntax (PHP CS Fixer)
composer lint

# 2. Run the complete test suite (Pest 3.x)
composer test
```

Any failures in tests or the linter must be flagged with **Major** or **Critical** severity.

---

## Step 5: Multi-Axis Audit & Quality Gates

In addition to CodeRabbit findings and automated test results, audit the diff against the 5 axes from `REVIEW.md`:

### Axis 1: PHP 8.2 Compatibility & Strict Typing
- **PHP 8.2 Baseline:** Are any PHP 8.3+ exclusive features used? (Forbidden: typed class constants `const string FOO`, `#[\Override]`, native `json_validate` without polyfill).
- **Strict Types:** Does every new or edited PHP file include `declare(strict_types=1);` at the top?
- **Immutability:** Do DTOs use `final class` declarations with `public readonly` properties?
- **Safe Enums:** Do enums provide an `Unknown = 'unknown'` fallback case and a static `tryFromValue()` method to absorb new WhatsApp features without uncaught exceptions?

### Axis 2: Security & Anti-SSRF (Zero Tolerance)
- **Anti-SSRF:** Do external URLs or paths pass through `GowaHost::validate()` prior to making HTTP calls?
- **HMAC Signatures:** Are webhooks validated with `WebhookSignature::verify()` using constant-time string comparison (`hash_equals`)?
- **Secret Leaks:** Are API tokens or webhook secrets exposed in logs, exceptions, or query parameters?

### Axis 3: Resilient Parsing & Data Robustness
- **Factory Methods (`fromArray`):**
  - Do they handle empty or invalid inputs (e.g., `['question' => 123]`) by returning `null`?
  - Are geographic coordinates (`latitude`, `longitude`) validated using `is_finite()` and within `-90..90` and `-180..180`?
  - Are booleans safely parsed from `"true"` / `"false"` strings using `filter_var(..., FILTER_VALIDATE_BOOLEAN)`?
  - Are nested associative arrays (e.g., `phones`) filtered to discard malformed elements?
- **Null Safety:** Do chained method calls handle potential `null` returns before accessing object properties?

### Axis 4: HTTP Client, Routing & Pest (Zero Real Network)
- **Centralization:** Are all outbound GOWA API requests encapsulated inside `src/GowaClient.php`?
- **Mocked Tests:** Do tests use `withMockResponse()` / Guzzle's `MockHandler`? Making real outbound HTTP requests during tests is strictly forbidden.
- **Fluent Routing:** Do `when()` and `otherwise()` handlers in `WebhookEvent` and `IncomingMessage` operate atomically and halt after the first match?

### Axis 5: Documentation Parity & Commits
- **Bilingual Documentation:** Are new public methods or DTOs documented in both `README.md` (EN) and `README.pt.md` (PT)?
- **Changelog:** Are modifications summarized in `CHANGELOG.md` under the `[Unreleased]` section?
- **Conventional Commits:** Do commit messages follow standard prefixes (`feat:`, `fix:`, `docs:`, `test:`)?

---

## Step 6: High-Signal Filtering (Zero Bikeshedding)

Apply quality filtering before producing the report:

- **Keep:**
  - 🔴 **Critical**: Security vulnerabilities (SSRF, HMAC bypass), syntax/PHP 8.2 breaks, runtime crashes, breaking API changes.
  - 🟠 **Major**: Resilient parsing flaws, real network calls in tests, failing tests, linter failures, mutable DTOs.
  - 🟡 **Minor**: Missing null-safe operators, untested execution paths, outdated documentation.
- **Discard:**
  - Cosmetic code style issues that PHP-CS-Fixer fixes automatically via `composer lint:fix`.
  - Purely theoretical suggestions that do not impact library behavior or reliability.
  - CodeRabbit findings that are false positives caused by untracked or uncommitted local files.

---

## Step 7: Publishing and Presentation

### 1. Terminal / Chat Output
Present the summary categorized by severity following the `REVIEW.md` taxonomy:
- Executive summary of the review
- Table or list of findings containing:
  - Exact file path and line number with clickable link
  - Clear description of the issue and its real-world impact
  - Code snippet with the recommended fix

### 2. GitHub PR Publication (when requested or with `--post-comments` flag)
Submit the review directly to the GitHub PR using the GitHub CLI:

```bash
cat << 'JSON' > /tmp/pr_review.json
{
  "commit_id": "HEAD_SHA",
  "body": "## 🤖 Code Review — PR #NUMBER\n\nSUMMARY",
  "event": "COMMENT",
  "comments": [
    {
      "path": "src/Dto/LocationPayload.php",
      "line": 35,
      "side": "RIGHT",
      "body": "**[Major] Coordinate boundary validation**\n\nDescription..."
    }
  ]
}
JSON

gh api repos/OWNER/REPO/pulls/NUMBER/reviews --input /tmp/pr_review.json
rm -f /tmp/pr_review.json
```
