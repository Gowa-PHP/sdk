# Contributing to gowa-php

Thank you for considering contributing to `gowa-php`! We welcome contributions, bug reports, and feature suggestions from the community.

## Code of Conduct

Please be respectful, professional, and empathetic in all interactions across issues, pull requests, and discussions.

## Development Setup

To set up the development environment locally:

1. **Fork and clone** the repository:
   ```bash
   git clone https://github.com/aguinaldotupy/gowa-php.git
   cd gowa-php
   ```

2. **Install dependencies** via Composer:
   ```bash
   composer install
   ```

3. **Run the test suite** using Pest PHP:
   ```bash
   vendor/bin/pest
   ```

## Workflow & Guidelines

1. **Create a branch** for your feature or bug fix:
   ```bash
   git checkout -b feature/my-new-feature
   # or
   git checkout -b fix/issue-description
   ```

2. **Write tests** for your changes. All new features and bug fixes must include unit or feature tests using Pest PHP.

3. **Run tests** and ensure all tests pass cleanly:
   ```bash
   vendor/bin/pest
   ```

4. **Keep commits clean and descriptive** following conventional commit messages (e.g. `feat: add support for XYZ`, `fix: handle edge case in ABC`).

5. **Submit a Pull Request** against the `main` branch. Complete the provided Pull Request template.

## Security Vulnerabilities

If you discover a security vulnerability, please refer to our [Security Policy](SECURITY.md) instead of creating a public issue.

Thank you for helping make `gowa-php` better!
