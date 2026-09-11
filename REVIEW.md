# GOWA PHP SDK — Code Review Guidelines & Quality Gates

This document defines the official Code Review policy for the **GOWA PHP SDK** (`gowa-php`).
It serves as the technical validation constitution for both human reviewers and autonomous agents (Antigravity, Claude Code, CodeRabbit CLI, Codex, OpenCode).

---

## 1. Code Review Philosophy: High Signal (Zero Bikeshedding)

1. **Focus on What Matters**: The review exists to serve as the **guardian against bugs, compatibility breaks, performance regressions, and security vulnerabilities**. We avoid bikeshedding over cosmetic code style — formatting is strictly the responsibility of automated tooling (`composer lint` / `vendor/bin/php-cs-fixer`).
2. **Rigorous Verification Against Code**: Never raise an issue based solely on inspecting an isolated diff chunk. Always inspect adjacent code, verify PHP 8.2 compatibility, and cross-reference GOWA endpoint behavior before raising a finding. False positives erode trust in reviews.
3. **Actionable Suggestions**: Every finding must explain **why** it is a problem, **what the real impact** is, and provide a **concrete, ready-to-apply code solution**.

---

## 2. Severity Taxonomy

Every review comment must be classified into one of the following 4 levels:

### 🔴 Critical (Merge / Release Blocking)
- **Security**: SSRF vulnerabilities (bypassing `GowaHost` validation), leaking webhook secrets or authentication tokens in logs or exceptions, missing HMAC verification on webhook endpoints.
- **Fatal Compatibility Break**: Incompatibility with PHP 8.2 (usage of PHP 8.3+ exclusive features, such as typed class constants, `#[\Override]`, or native `json_validate` without polyfill).
- **Runtime Crash**: Syntax errors, nonexistent imports, method calls on `null` that cause unavoidable fatal crashes (`TypeError`, `Error`).
- **Contract Break**: Breaking or destructive changes in public DTOs or `GowaClient` methods without deprecation notices or a major version bump.

### 🟠 Major (Must Be Fixed Before Merge)
- **Parsing Logic & Resilience**: Incorrect parsing of WhatsApp/GOWA payload data (e.g., blind boolean casting where `"false"` evaluates to `true`, out-of-bounds geographic coordinates accepted without bounds check, unnormalized aliases resulting in empty/broken DTOs).
- **Performance & Memory**: Buffering massive media files or payloads in memory instead of leveraging streams or file paths in `MediaUpload`.
- **Architectural Violation**: Direct HTTP requests outside `src/GowaClient.php`, mutable DTOs (must be `readonly`), missing `declare(strict_types=1);`.
- **Live Network in Tests**: Tests making real external HTTP requests instead of using Guzzle's `MockHandler`.

### 🟡 Minor (Robustness & Maintainability)
- **Defensive Coding**: Missing null-safe operators (`?->`) when accessing optional payload properties that may evaluate to `null`.
- **Edge Cases & Enums**: Event or message type enums missing an `Unknown` case or safe fallback in `tryFromValue()`, which could crash when WhatsApp introduces new message types.
- **Test Coverage**: New endpoint, client method, DTO, or message type without corresponding Pest tests in `tests/Unit/` or `tests/Feature/`.

### 💡 Suggestion / Nitpick (Optional / Non-Blocking)
- Readability improvements, algorithm simplifications, or PHPDoc array shape enhancements for future technical debt.

---

## 3. Quality Gates — GOWA PHP SDK (`src/` — PHP 8.2+, Pest 3.x)

### 🔒 Security & Anti-SSRF (Zero Tolerance)
- [ ] **Host Validation (Anti-SSRF)**: Any external URL provided (e.g., remote uploads via `fromUrl`) or relative paths must be strictly validated against `GowaHost::validate()`, ensuring only the configured GOWA server host is accessed.
- [ ] **Webhook Signature (HMAC SHA-256)**: Payload verification with `WebhookSignature::verify()` using constant-time string comparison (`hash_equals`), supporting both raw hex strings and the optional `sha256=` prefix.
- [ ] **Secret Protection**: Webhook secrets and API authorization tokens must never be exposed in public exception messages, query strings, or plaintext logs.

### 🐘 PHP 8.2 Compatibility & Strict Typing
- [ ] **Strict PHP 8.2 Baseline**: The project supports PHP 8.2 through 8.5. Usage of features exclusive to PHP 8.3+ is strictly forbidden (e.g., typed class constants, `#[\Override]`, `json_validate`).
- [ ] **Strict Types**: Every PHP file in `src/` and `tests/` MUST start with `declare(strict_types=1);`.
- [ ] **DTO Immutability**: Value objects and DTOs must use `final class` declarations with `public readonly` properties.
- [ ] **Resilient Factory Methods (`fromArray`)**:
  - Validate field types prior to instantiation; return `null` when no meaningful/valid payload fields are provided.
  - Normalize booleans using `filter_var($val, FILTER_VALIDATE_BOOLEAN)`.
  - Validate geographic coordinate boundaries (`-90.0 <= lat <= 90.0` and `-180.0 <= lng <= 180.0`) and finiteness (`is_finite()`).
  - Filter nested associative arrays (e.g., `phones` in `ContactCard`) preserving only items with expected structures.
- [ ] **Safe Enum Fallbacks**: Enums such as `Event` and `MessageType` must provide an `Unknown = 'unknown'` case and a `tryFromValue(string $value): self` helper to gracefully handle unknown WhatsApp updates without uncaught exceptions.

### ⚡ HTTP Client & Webhooks
- [ ] **Centralized HTTP Behavior**: All external REST API communication lives strictly within `src/GowaClient.php`.
- [ ] **Fluent & Resilient Routing**: The `WebhookEvent` and `IncomingMessage` dispatchers must provide atomic, fluent routing (`when()`, `otherwise()`) that gracefully handles unhandled or malformed payloads.
- [ ] **ArrayAccess Backwards Compatibility**: Webhook event results must implement `ArrayAccess` to maintain 100% backward compatibility with traditional array indexing (`$parsed['data']`, `$parsed['event']`).

### 🧪 Automated Testing (Pest 3.x)
- [ ] **Zero Real Network Calls**: Feature tests must mock GOWA API responses using the `withMockResponse()` helper or Guzzle's `MockHandler`.
- [ ] **Isolated Unit Tests**: DTOs, webhook parsing, security checks, and configuration logic must be covered in `tests/Unit/`.
- [ ] **Feature Tests**: Client request/response flows must be covered in `tests/Feature/`.
- [ ] **100% Passing Suite**: All tests must pass cleanly via `composer test` or `vendor/bin/pest`.

### 🎨 Code Style & Formatting
- [ ] **Clean PHP-CS-Fixer**: The codebase must pass cleanly with zero warnings under `composer lint` (`vendor/bin/php-cs-fixer fix --dry-run --diff`). Use `composer lint:fix` for automatic correction.

### 📝 Documentation & Conventional Commits
- [ ] **Documentation Parity**: Any new endpoint, DTO, or public feature must be documented in both `README.md` (English) and `README.pt.md` (Portuguese).
- [ ] **Updated Changelog**: Changes must be logged in `CHANGELOG.md` under the `[Unreleased]` section.
- [ ] **Conventional Commits**: Commit messages must adhere to standard prefixes (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`).

---

## 4. Standard Format for Review Findings

When submitting review comments on a PR or generating a review summary, adhere to the following template:

```markdown
### [SEVERITY] `path/to/File.php:Line` — Descriptive Title

**Problem:**
Clear explanation of the bug, vulnerability, or compatibility issue, detailing the real-world impact.

**Root Cause:**
```php
// problematic code snippet
```

**Suggested Fix:**
```php
// corrected code, ready to apply
```
```
