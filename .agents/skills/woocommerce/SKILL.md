---
name: woocommerce
description: Comprehensive skill for WooCommerce development, covering backend PHP standards, code review, UI copy guidelines, development lifecycle, and documentation. Use for any WooCommerce-related project changes.
compatibility: "Targets WordPress 6.9+ (PHP 7.2.24+) and WooCommerce core conventions. Filesystem-based agent with bash + node."
---

# WooCommerce Development

This skill provides comprehensive guidance for WooCommerce development, including architecture, coding standards, testing, and UI copy.

## Core Areas

### 1. Backend PHP Development
Guidelines for creating classes, methods, and hooks following WooCommerce core conventions.
- [General Backend Guide](references/backend-dev-guide.md)
- [Naming Conventions (Classes, Methods, Variables)](references/code-entities.md)
- [File Organization & PSR-4](references/file-entities.md)
- [Coding Standards (WP-based)](references/coding-conventions.md)
- [Dependency Injection](references/dependency-injection.md)
- [Managing Hooks & Callbacks](references/hooks.md)
- [Data Integrity & CRUD Operations](references/data-integrity.md)
- [WooCommerce Global Objects & Functions](references/woocommerce-global-objects.md)
- [PHP i18n Patterns](references/php-i18n-patterns.md)
- [Security Patterns (Sanitization/Escaping)](references/security-patterns.md)
- [PHPStan-aware Type Annotations](references/type-annotations.md)

### 2. Development Lifecycle & Automation
Tools and patterns for ensuring high-quality code.
- [Dev Cycle & General Best Practices](references/dev-cycle-guide.md)
- [PHP Linting Patterns](references/php-linting-patterns.md)
- [JS i18n Patterns](references/js-i18n-patterns.md)
- [Running & Writing Tests](references/running-tests.md)
- [Code Quality Checklist](references/code-quality.md)

### 3. Code Review Standards
Criteria for reviewing WooCommerce code changes.
- [Code Review Checklist](references/code-review-guide.md)

### 4. UI Copy & Guidelines
Standards for consistency in user-facing text.
- [UI Copy Principles](references/copy-guidelines-guide.md)
- [Sentence Case Guidelines](references/sentence-case.md)

### 5. Documentation & Markdown
- [Markdown Linting & Standards](references/markdown-guide.md)
- [Markdown Style Guide](references/markdown-linting.md)

## Key Principles

- **Automatic Verification**: Always run linting and tests before finalizing changes.
- **WP Coding Standards**: Strictly adhere to WordPress and WooCommerce specific coding standards.
- **Data Safety**: Validate state and permissions before performing destructive operations.
- **Consistency**: Follow existing naming and structural patterns in the codebase.
- **Documentation**: Provide appropriate `@since` and `@testdox` annotations.
