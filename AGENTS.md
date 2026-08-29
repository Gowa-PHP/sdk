# Repository Guidelines

## Project Structure & Module Organization

This is a PHP SDK for GOWA. Production code lives in `src/` under the `Gowa\Sdk\` PSR-4 namespace. Keep HTTP client behavior in `src/GowaClient.php`, configuration in `src/Config.php`, and group value objects in `src/Dto/`, webhook parsing in `src/Webhook/`, security checks in `src/Security/`, and domain errors in `src/Exceptions/`.

Tests use the same broad structure in `tests/Unit/` and `tests/Feature/`. Shared Pest helpers are defined in `tests/Pest.php`. Public usage and endpoint documentation belong in `README.md` and, when applicable, `README.pt.md`.

## Build, Test, and Development Commands

Install the locked development dependencies with:

```bash
composer install
```

Run the full Pest suite with either command:

```bash
composer test
vendor/bin/pest
```

CI runs this suite for PHP 8.2 through 8.5. Run tests before opening a pull request.

## Coding Style & Naming Conventions

Use PHP 8.2-compatible code, `declare(strict_types=1);`, four-space indentation, and the existing formatting style. Classes, enums, and DTOs use PascalCase (`MediaPayload`); methods and properties use camelCase (`startQrPairing`); constants use SCREAMING_SNAKE_CASE.

Keep source files focused and match the namespace to their directory. Add scalar and object type declarations, prefer immutable `readonly` data where appropriate, and document non-obvious array shapes with PHPDoc. No formatter or linter is configured, so follow neighboring code closely.

## Testing Guidelines

Write Pest tests for every feature or bug fix. Name files `*Test.php` and use readable behavior-focused test descriptions, for example `test('config detects whether credentials and url are properly set', ...)`. Place isolated parsing, DTO, configuration, and security behavior in `tests/Unit/`; place client request/response flows in `tests/Feature/`. Mock HTTP with the helpers and Guzzle `MockHandler` patterns already used in the suite; do not call live GOWA services.

## Commit & Pull Request Guidelines

Use concise Conventional Commit-style subjects, such as `feat: add webhook event parser`, `fix: reject invalid media type`, or `docs: update setup instructions`. Keep each commit scoped to one logical change.

Target pull requests at `main`. Complete the PR template: describe the change, select its type, link related issues, include tests and documentation updates, and paste clean `vendor/bin/pest` output. Add screenshots only when a documentation or visual asset change benefits from one. Report security vulnerabilities privately through the process in `SECURITY.md`, not in public issues.
